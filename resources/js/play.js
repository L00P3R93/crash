import { createEcho } from './echo';
import { createFlightChart } from './flight-chart';

const TOKEN_STORAGE_KEY = 'aviator_token';
const SOUND_STORAGE_KEY = 'aviator_sound_enabled';

const el = {
    authScreen: document.getElementById('auth-screen'),
    gameScreen: document.getElementById('game-screen'),
    loginForm: document.getElementById('login-form'),
    loginMsisdn: document.getElementById('login-msisdn'),
    loginPin: document.getElementById('login-pin'),
    loginError: document.getElementById('login-error'),
    loginErrorText: document.getElementById('login-error-text'),
    playerMsisdn: document.getElementById('account-panel-msisdn'),
    logoutBtn: document.getElementById('account-panel-signout-btn'),
    soundToggleBtn: document.getElementById('account-panel-sound-toggle-btn'),
    soundOnIcon: document.getElementById('account-panel-sound-on-icon'),
    soundOffIcon: document.getElementById('account-panel-sound-off-icon'),
    roundNumber: document.getElementById('round-number'),
    statusBadges: {
        idle: document.getElementById('status-idle'),
        betting: document.getElementById('status-betting'),
        running: document.getElementById('status-running'),
        crashed: document.getElementById('status-crashed'),
        settled: document.getElementById('status-settled'),
    },
    multiplierDisplay: document.getElementById('multiplier-display'),
    bettingCountdown: document.getElementById('betting-countdown'),
    crashReveal: document.getElementById('crash-reveal'),
    flightSweep: document.getElementById('flight-sweep'),
    flightPath: document.getElementById('flight-path'),
    flightFill: document.getElementById('flight-fill'),
    activePlayersBody: document.getElementById('active-players-body'),
    activePlayersPrevBtn: document.getElementById('active-players-prev-btn'),
    activePlayersNextBtn: document.getElementById('active-players-next-btn'),
    activePlayersPageLabel: document.getElementById('active-players-page-label'),
    betPanel: document.getElementById('bet-panel'),
    stakeInput: document.getElementById('stake-input'),
    stakeHint: document.getElementById('stake-hint'),
    placeBetBtn: document.getElementById('place-bet-btn'),
    placeBetBtnLabel: document.getElementById('place-bet-btn-label'),
    betError: document.getElementById('bet-error'),
    betErrorText: document.getElementById('bet-error-text'),
    activeBetPanel: document.getElementById('active-bet-panel'),
    activeBetStake: document.getElementById('active-bet-stake'),
    potentialPayout: document.getElementById('potential-payout'),
    cashoutBtn: document.getElementById('cashout-btn'),
    cashoutBtnLabel: document.getElementById('cashout-btn-label'),
    cashoutError: document.getElementById('cashout-error'),
    cashoutErrorText: document.getElementById('cashout-error-text'),
    resultBanner: document.getElementById('result-banner'),
    resultIconWon: document.getElementById('result-icon-won'),
    resultIconLost: document.getElementById('result-icon-lost'),
    resultText: document.getElementById('result-text'),
    accountFab: document.getElementById('account-fab'),
    accountPanelOverlay: document.getElementById('account-panel-overlay'),
    accountPanel: document.getElementById('account-panel'),
    accountPanelBackdrop: document.getElementById('account-panel-backdrop'),
    accountPanelCloseBtn: document.getElementById('account-panel-close-btn'),
    walletModalSubmitBtn: document.getElementById('wallet-modal-submit-btn'),
    walletModalSubmitLabel: document.getElementById('wallet-modal-submit-label'),
    walletModalMessage: document.getElementById('wallet-modal-message'),
    walletModalMessageText: document.getElementById('wallet-modal-message-text'),
};

const state = {
    token: localStorage.getItem(TOKEN_STORAGE_KEY),
    player: null,
    round: null,
    bet: null,
    // Identity of the bet still awaiting a final win/loss outcome, tracked
    // separately from `bet` (which is the *displayed* bet for the current
    // round and gets cleared the instant betting reopens). A round can
    // settle and hand off to its successor's betting_opened broadcast
    // before the settled round's own broadcast arrives — without this,
    // that reset would wipe the only reference needed to resolve the old
    // bet, silently dropping it from the player's round history.
    pendingBet: null,
    echo: null,
    hasConnectedBefore: false,
    rafId: null,
    countdownId: null,
    soundEnabled: localStorage.getItem(SOUND_STORAGE_KEY) === '1',
    walletTab: 'topup',
    // Every bet placed on the current round, keyed by bet_id, in arrival
    // order — the roster behind the "Active players this round" table.
    // Cleared on round.betting_opened, populated by
    // feed.bet_placed/feed.bet_cashed_out as they arrive, and reconciled
    // entry-by-entry by round.results once the round settles (the
    // authoritative final state, covering any bet that never cashed out
    // and any feed broadcast this client missed).
    activePlayers: new Map(),
    // 0-indexed page of `activePlayers` currently on screen (10 per page).
    activePlayersPage: 0,
};

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const flightChart = createFlightChart(el.flightPath, el.flightFill);

// --- Sound (synthesized — no audio assets to ship or license) ---

let audioContext = null;

function playTone(frequency, durationMs) {
    if (!state.soundEnabled) {
        return;
    }

    audioContext ??= new (window.AudioContext || window.webkitAudioContext)();

    const oscillator = audioContext.createOscillator();
    const gain = audioContext.createGain();

    oscillator.type = 'sine';
    oscillator.frequency.value = frequency;
    gain.gain.setValueAtTime(0.12, audioContext.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + durationMs / 1000);

    oscillator.connect(gain).connect(audioContext.destination);
    oscillator.start();
    oscillator.stop(audioContext.currentTime + durationMs / 1000);
}

function playWinTone() {
    playTone(880, 220);
}

function playCrashTone() {
    playTone(160, 260);
}

function renderSoundToggle() {
    el.soundToggleBtn.setAttribute('aria-pressed', String(state.soundEnabled));
    el.soundOnIcon.classList.toggle('hidden', !state.soundEnabled);
    el.soundOffIcon.classList.toggle('hidden', state.soundEnabled);
}

function toggleSound() {
    state.soundEnabled = !state.soundEnabled;
    localStorage.setItem(SOUND_STORAGE_KEY, state.soundEnabled ? '1' : '0');
    renderSoundToggle();
}

// --- API helper ---

function api(path, options = {}) {
    return fetch(`/api/${path}`, {
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(state.token ? { Authorization: `Bearer ${state.token}` } : {}),
            ...(options.headers ?? {}),
        },
    }).then(async (response) => {
        const body = await response.json().catch(() => ({}));

        if (!response.ok) {
            const error = new Error(body.message ?? Object.values(body.errors ?? {})[0]?.[0] ?? 'Something went wrong.');
            error.status = response.status;

            throw error;
        }

        return body;
    });
}

function setError(container, textEl, message) {
    textEl.textContent = message ?? '';
    container.classList.toggle('hidden', !message);
    container.classList.toggle('flex', Boolean(message));
}

/**
 * Mirrors App\Support\MsisdnMasker::mask() — shown in the account panel so
 * a player's own number doesn't sit fully exposed on screen (e.g. over a
 * shoulder) just because it's their own account.
 */
function maskMsisdn(msisdn) {
    if (!msisdn || msisdn.length < 9) {
        return msisdn;
    }

    return `${msisdn.slice(0, 6)}***${msisdn.slice(-3)}`;
}

// --- Auth ---

async function login(msisdn, pin) {
    const body = await api('auth/login', {
        method: 'POST',
        body: JSON.stringify({ msisdn, pin, device_name: 'web' }),
    });

    state.token = body.token;
    state.player = body.player;
    localStorage.setItem(TOKEN_STORAGE_KEY, state.token);

    await enterGame();
}

async function logout() {
    stopMultiplierLoop();
    state.echo?.disconnect();
    state.echo = null;

    try {
        await api('auth/logout', { method: 'POST' });
    } catch {
        // Token may already be invalid — logging out locally still succeeds.
    }

    state.token = null;
    state.player = null;
    state.round = null;
    state.bet = null;
    state.pendingBet = null;
    state.hasConnectedBefore = false;
    localStorage.removeItem(TOKEN_STORAGE_KEY);

    // The account panel is a global overlay, not scoped inside game-screen
    // — showAuthScreen() alone doesn't touch it, so without this it stays
    // open (and its wallet/sign-out controls interactive) right on top of
    // the login form.
    closeAccountPanel();
    showAuthScreen();
}

async function refreshPlayer() {
    state.player = await api('auth/me');
    renderPlayer();
}

async function enterGame() {
    showGameScreen();
    renderPlayer();
    renderSoundToggle();

    // Subscribe before the reconciling REST fetch below, not after — any
    // round event that fires in the gap between them is otherwise lost for
    // good (Echo doesn't buffer events from before a subscription existed).
    // With the fetch as the second step, at worst an event fires between
    // the two and gets harmlessly overwritten by the fetch's own snapshot.
    connectRealtime();

    const config = await api('aviator/config');
    el.stakeHint.textContent = `${(config.rtp * 100).toFixed(0)}% RTP · Up to ${config.max_multiplier}x · Stake KSh ${config.min_stake}-${config.max_stake}`;
    el.stakeInput.min = config.min_stake;
    el.stakeInput.max = config.max_stake;

    await resyncGameState();
}

/**
 * Fetches the authoritative round/bet snapshot and applies it as the new
 * baseline. Used both for the initial load and to recover after a
 * WebSocket reconnect, when broadcasts fired during the outage were missed
 * for good and only a fresh REST read can catch the client back up.
 */
async function resyncGameState() {
    const [{ data: round }, { data: activeBet }, { data: roundPlayers }] = await Promise.all([
        api('aviator/round'),
        api('aviator/bets/active'),
        api('aviator/round/players'),
    ]);

    state.round = round;

    state.activePlayers.clear();
    (roundPlayers?.players ?? []).forEach((player) => {
        state.activePlayers.set(player.bet_id, {
            betId: player.bet_id,
            msisdn: player.msisdn,
            stake: player.stake,
            status: player.status === 'active' || player.status === 'pending' ? 'pending' : (player.status === 'won' ? 'won' : 'lost'),
            cashout_multiplier: player.cashout_multiplier,
            payout: player.payout,
        });
    });
    state.activePlayersPage = 0;
    renderActivePlayersPage();

    // A bet can only be 'active' on the round currently in flight — the
    // game loop runs one round to full completion before starting the
    // next — so this is always the same round `round` above just described,
    // whether we're loading fresh or recovering mid-round after a reload.
    if (activeBet) {
        state.bet = activeBet;
        state.pendingBet = {
            id: activeBet.id,
            bet_reference: activeBet.bet_reference,
            round_number: activeBet.round_number,
        };
    } else {
        state.bet = null;
        state.pendingBet = null;
    }

    renderRound();

    // Joining (or reloading) mid-round only gets the multiplier moving again
    // via this fetch — the animation otherwise only starts from the
    // 'round.started' broadcast, which already fired before we connected.
    stopMultiplierLoop();

    if (round?.status === 'running') {
        startMultiplierLoop();
    }
}

function isCurrentRound(payload) {
    return payload.round_number === state.round?.round_number;
}

function connectRealtime() {
    state.echo = createEcho(state.token);

    // pusher-js auto-reconnects on its own after a drop, but reconnecting
    // the socket doesn't replay whatever broadcasts fired while it was
    // down — those are gone for good. Treat every 'connected' after the
    // first as a signal to re-fetch the authoritative state from REST
    // rather than trusting whatever the client was last showing.
    state.echo.connector.pusher.connection.bind('connected', () => {
        if (state.hasConnectedBefore) {
            resyncGameState();
        }

        state.hasConnectedBefore = true;
    });

    state.echo.channel('aviator.rounds')
        .listen('.round.betting_opened', (payload) => {
            // If the previous round's `.round.crashed` broadcast never
            // arrived (dropped, or the server moved on before anyone was
            // listening), its requestAnimationFrame loop is still ticking
            // and will keep overwriting the display this renderRound() call
            // is about to reset — stop it explicitly rather than relying on
            // the crashed handler alone.
            stopMultiplierLoop();

            state.round = { ...payload, status: 'betting' };
            // Only the *displayed* bet resets here — `pendingBet` (the old
            // round's still-unresolved bet, if any) is deliberately left
            // alone. A fast, low-multiplier round can settle and reopen
            // betting before its own `.round.settled` broadcast arrives;
            // clearing pendingBet here too would drop that bet's outcome
            // from the player's round history for good.
            state.bet = null;
            hideResultBanner();
            flightChart.reset();
            renderRound();
            playAltimeterSweep();

            // The live-players table is scoped to a single round — clear it
            // the instant a new one opens for betting.
            clearActivePlayers();
        })
        .listen('.round.started', (payload) => {
            // A round can crash and re-open betting for its successor before
            // this event is delivered (the game loop moves on the instant it
            // crashes, with no wait for anyone to be listening). Applying a
            // stale started/crashed/settled event here would regress the
            // displayed round back to a dead one.
            if (!isCurrentRound(payload)) {
                return;
            }

            state.round = { ...state.round, ...payload, status: 'running' };
            renderRound();
            startMultiplierLoop();
        })
        .listen('.round.crashed', (payload) => {
            if (!isCurrentRound(payload)) {
                return;
            }

            state.round = { ...state.round, ...payload, status: 'crashed' };
            stopMultiplierLoop();
            renderRound();
        })
        .listen('.round.settled', (payload) => {
            // Resolve whichever bet was pending on THIS round regardless of
            // whether the display has already moved on to a newer one — see
            // the betting_opened handler's comment for why this can't be
            // gated behind isCurrentRound() the way the display update is.
            if (state.pendingBet?.round_number === payload.round_number) {
                const pendingBet = state.pendingBet;
                state.pendingBet = null;
                // Only safe to ask "did my bet survive?" once settled —
                // settling is what actually marks a still-active bet lost
                // server-side; asking right on the crash event can race
                // ahead of that write and read the bet back as still
                // 'active'.
                resolveBetAfterSettlement(pendingBet);
            }

            if (!isCurrentRound(payload)) {
                return;
            }

            state.round = { ...state.round, ...payload, status: 'settled' };
            renderRound();
        })
        .listen('.feed.bet_placed', (payload) => {
            if (!isCurrentRound(payload)) {
                return;
            }

            upsertActivePlayer({
                betId: payload.bet_id,
                msisdn: payload.msisdn,
                stake: payload.stake,
                status: 'pending',
                cashout_multiplier: null,
                payout: null,
            });
        })
        .listen('.feed.bet_cashed_out', (payload) => {
            if (!isCurrentRound(payload)) {
                return;
            }

            // The client may not have an entry yet (e.g. it connected after
            // this player's feed.bet_placed already fired) — upsert either
            // way. cashed_out never carries the stake, so a synthesized row
            // just shows 0 for it, corrected on the next round.results sync.
            const existing = state.activePlayers.get(payload.bet_id) ?? {
                betId: payload.bet_id,
                msisdn: payload.msisdn,
                stake: 0,
            };
            upsertActivePlayer({
                ...existing,
                status: 'won',
                cashout_multiplier: payload.cashout_multiplier,
                payout: payload.payout,
            });
        })
        .listen('.round.results', (payload) => {
            if (!isCurrentRound(payload)) {
                return;
            }

            // The authoritative final state for every bet in the round —
            // an "In play" bet that never cashed out flips straight to red.
            payload.players.forEach((player) => {
                upsertActivePlayer({
                    betId: player.bet_id,
                    msisdn: player.msisdn,
                    stake: player.stake,
                    status: player.status === 'won' ? 'won' : 'lost',
                    cashout_multiplier: player.cashout_multiplier,
                    payout: player.payout,
                });
            });
        });

    state.echo.private(`aviator.player.${state.player.id ?? ''}`)
        .listen('.bet.placed', (payload) => {
            // A fast, low-multiplier round can crash and settle before this
            // broadcast even arrives — by then the client has already moved
            // on to the next round's betting_opened, which reset state.bet
            // to null. Without this check, a bet.placed straggler for the
            // dead round would resurrect an already-lost bet's "Cash out"
            // button on the new round.
            if (!isCurrentRound(payload)) {
                return;
            }

            state.bet = { ...state.bet, ...payload, status: 'active' };
            state.pendingBet = {
                id: state.bet.id,
                bet_reference: payload.bet_reference,
                round_number: payload.round_number,
            };
            renderBet();
        })
        .listen('.bet.cashed_out', async (payload) => {
            // Keyed on the bet reference (this event doesn't carry a round
            // number) rather than isCurrentRound, and checked against
            // pendingBet as well as the displayed bet — an auto-cashout can
            // confirm after betting_opened for the next round has already
            // cleared `state.bet`, same class of race as the settled case
            // above.
            const isPendingBet = payload.bet_reference === state.pendingBet?.bet_reference;
            const isDisplayedBet = payload.bet_reference === state.bet?.bet_reference;

            if (!isPendingBet && !isDisplayedBet) {
                return;
            }

            state.pendingBet = null;

            if (isDisplayedBet) {
                state.bet = { ...state.bet, ...payload, status: 'won' };
                renderBet();
                renderRound();
            }

            showResultBanner('won', payload);
            playWinTone();
            await refreshPlayer();
        });
}

function playAltimeterSweep() {
    if (prefersReducedMotion) {
        return;
    }

    el.flightSweep.classList.remove('aviator-sweep');
    // Force reflow so re-adding the class restarts the animation on repeat
    // triggers — assigning the same class name twice in a row is a no-op
    // otherwise.
    void el.flightSweep.offsetWidth;
    el.flightSweep.classList.add('aviator-sweep');
}

function startMultiplierLoop() {
    const startedAtMs = new Date(state.round.started_at).getTime();
    const k = state.round.acceleration_k;

    const tick = () => {
        const elapsedSeconds = Math.max(0, (Date.now() - startedAtMs) / 1000);
        const multiplier = Math.exp(k * elapsedSeconds);

        el.multiplierDisplay.textContent = `${multiplier.toFixed(2)}x`;
        flightChart.draw(elapsedSeconds, multiplier);

        if (state.bet?.status === 'active') {
            el.potentialPayout.textContent = `KSh ${(Number(state.bet.stake) * multiplier).toFixed(2)}`;
        }

        state.rafId = requestAnimationFrame(tick);
    };

    tick();
}

function stopMultiplierLoop() {
    if (state.rafId) {
        cancelAnimationFrame(state.rafId);
        state.rafId = null;
    }
}

function startBettingCountdownLoop(bettingClosesAt) {
    stopBettingCountdownLoop();

    const closesAtMs = new Date(bettingClosesAt).getTime();
    let lastRendered = null;

    const tick = () => {
        const remaining = Math.max(0, Math.round((closesAtMs - Date.now()) / 1000));

        if (remaining !== lastRendered) {
            lastRendered = remaining;
            el.bettingCountdown.textContent = remaining > 0
                ? `Place your bet — closes in ${remaining}s`
                : 'Betting closes any moment…';
        }

        if (remaining <= 0) {
            state.countdownId = null;
            return;
        }

        state.countdownId = requestAnimationFrame(tick);
    };

    tick();
}

function stopBettingCountdownLoop() {
    if (state.countdownId) {
        cancelAnimationFrame(state.countdownId);
        state.countdownId = null;
    }
}

/**
 * @param {{id: number, bet_reference: string, round_number: number}} pendingBet
 *   Snapshot taken before the settled round's own `betting_opened`
 *   successor could clear `state.bet` out from under this call — never
 *   re-read `state.bet.id` here, it may already refer to a different bet.
 */
async function resolveBetAfterSettlement(pendingBet) {
    // The bet only gets a push event on a manual cash-out — a bet that rode
    // the round all the way to the crash is settled server-side but never
    // announced, so ask directly rather than waiting for an event that
    // isn't coming.
    const { data: bet } = await api(`aviator/bets/${pendingBet.id}`);

    // Only touch the live display if it's still showing this exact bet —
    // the player may have already placed a new one on a later round while
    // this was in flight.
    if (state.bet?.id === pendingBet.id) {
        state.bet = bet;
        renderBet();
    }

    if (bet.status === 'lost') {
        showResultBanner('lost', bet);
        playCrashTone();
    }

    await refreshPlayer();
}

async function placeBet() {
    setError(el.betError, el.betErrorText, null);
    // Flips instantly on click, before the request even goes out, so the
    // button never sits there looking unresponsive during the round trip.
    el.placeBetBtn.disabled = true;
    el.placeBetBtnLabel.textContent = 'Placing bet…';

    try {
        const { data: bet } = await api('aviator/bets', {
            method: 'POST',
            headers: { 'Idempotency-Key': crypto.randomUUID() },
            body: JSON.stringify({ stake: Number(el.stakeInput.value) }),
        });

        state.bet = bet;
        // A successful bet is always accepted against the round currently
        // open for betting — the one this client has displayed as
        // state.round — regardless of whether the API response itself
        // carries a round_number (it doesn't; the bet's round relation
        // isn't eager-loaded on this endpoint).
        state.pendingBet = {
            id: bet.id,
            bet_reference: bet.bet_reference,
            round_number: state.round.round_number,
        };
        renderBet();
        await refreshPlayer();
    } catch (error) {
        setError(el.betError, el.betErrorText, error.message);
    } finally {
        el.placeBetBtn.disabled = false;
        el.placeBetBtnLabel.textContent = 'Place bet';
    }
}

async function cashOut() {
    setError(el.cashoutError, el.cashoutErrorText, null);
    // Flips instantly on click, before the request even goes out — this is
    // the button players complain feels laggy, so it gets visible feedback
    // the same frame it's pressed rather than only a disabled state.
    el.cashoutBtn.disabled = true;
    el.cashoutBtnLabel.textContent = 'Cashing out…';

    try {
        const { data: bet } = await api(`aviator/bets/${state.bet.id}/cashout`, { method: 'POST' });
        state.bet = bet;
        state.pendingBet = null;
        renderBet();
        renderRound();
        showResultBanner('won', bet);
        playWinTone();
        await refreshPlayer();
    } catch (error) {
        setError(el.cashoutError, el.cashoutErrorText, error.message);
        el.cashoutBtn.disabled = false;
    } finally {
        el.cashoutBtnLabel.textContent = 'Cash out';
    }
}

// --- Rendering ---

function renderPlayer() {
    el.playerMsisdn.textContent = maskMsisdn(state.player.msisdn);

    document.querySelectorAll('[data-wallet-balance-amount]').forEach((node) => {
        node.textContent = `KSh ${Number(state.player.balance).toFixed(2)}`;
    });
}

function setStatusBadge(status) {
    for (const [key, badge] of Object.entries(el.statusBadges)) {
        badge.style.display = key === status ? 'inline-flex' : 'none';
    }
}

function renderRound() {
    const round = state.round;
    const status = round?.status ?? 'idle';

    el.roundNumber.textContent = round?.round_number ? `#${round.round_number}` : '—';
    setStatusBadge(status);

    const isBetting = status === 'betting';
    const isRunning = status === 'running';
    const hasCrashed = status === 'crashed' || status === 'settled';
    const hasCashedOut = state.bet?.status === 'won';

    el.bettingCountdown.classList.toggle('hidden', !isBetting);
    el.crashReveal.classList.toggle('hidden', !hasCrashed);

    if (isBetting && round?.betting_closes_at) {
        startBettingCountdownLoop(round.betting_closes_at);
    } else {
        stopBettingCountdownLoop();
    }

    el.multiplierDisplay.classList.remove(
        'aviator-multiplier--idle',
        'aviator-multiplier--live',
        'aviator-multiplier--crashed',
        'aviator-multiplier--cashed-out',
    );

    if (hasCrashed) {
        el.crashReveal.textContent = `Crashed at ${Number(round.crash_multiplier).toFixed(2)}x`;
        el.multiplierDisplay.textContent = `${Number(round.crash_multiplier).toFixed(2)}x`;
        el.multiplierDisplay.classList.add('aviator-multiplier--crashed');
    } else if (isRunning && hasCashedOut) {
        // The round keeps climbing for everyone else, but this player has
        // already locked in their result — their own view reflects that.
        el.multiplierDisplay.classList.add('aviator-multiplier--cashed-out');
    } else if (isRunning) {
        el.multiplierDisplay.classList.add('aviator-multiplier--live');
    } else {
        el.multiplierDisplay.textContent = '1.00x';
        el.multiplierDisplay.classList.add('aviator-multiplier--idle');
    }

    // A bet may only be placed while betting is open and none is active yet.
    el.betPanel.classList.toggle('hidden', !isBetting || Boolean(state.bet && state.bet.status === 'active'));
    renderBet();
}

function renderBet() {
    const bet = state.bet;
    const isActive = bet && bet.status === 'active';

    el.activeBetPanel.classList.toggle('hidden', !isActive);
    el.betPanel.classList.toggle('hidden', Boolean(isActive) || state.round?.status !== 'betting');

    if (isActive) {
        el.activeBetStake.textContent = `KSh ${Number(bet.stake).toFixed(2)}`;
        el.potentialPayout.textContent = `KSh ${Number(bet.stake).toFixed(2)}`;
        el.cashoutBtn.disabled = state.round?.status !== 'running';
    }
}

function showResultBanner(outcome, bet) {
    el.resultBanner.style.display = 'flex';
    el.resultBanner.dataset.outcome = outcome;
    el.resultIconWon.classList.toggle('hidden', outcome !== 'won');
    el.resultIconLost.classList.toggle('hidden', outcome !== 'lost');

    el.resultText.textContent = outcome === 'won'
        ? `WON — cashed out at ${Number(bet.cashout_multiplier).toFixed(2)}x, paid KSh ${Number(bet.payout).toFixed(2)}`
        : `Crashed before you cashed out — lost KSh ${Number(bet.stake).toFixed(2)}`;
}

function hideResultBanner() {
    el.resultBanner.style.display = 'none';
}

const ACTIVE_PLAYERS_PER_PAGE = 10;

function orderedActivePlayers() {
    return Array.from(state.activePlayers.values());
}

function totalActivePlayersPages() {
    return Math.max(1, Math.ceil(orderedActivePlayers().length / ACTIVE_PLAYERS_PER_PAGE));
}

function buildActivePlayerRow() {
    const row = document.createElement('tr');
    row.className = 'border-t border-white/5';
    row.innerHTML = `
        <td class="py-1.5 text-casino-white/80" data-cell="player"></td>
        <td class="py-1.5 text-casino-white/80" data-cell="stake"></td>
        <td class="py-1.5 text-right font-medium" data-cell="result"></td>
    `;

    return row;
}

/**
 * @param {{betId: number, msisdn: string, stake: number, status: 'pending'|'won'|'lost', cashout_multiplier: number|null, payout: number|null}} entry
 */
function fillActivePlayerRow(row, entry) {
    row.dataset.betId = String(entry.betId);
    row.querySelector('[data-cell="player"]').textContent = entry.msisdn;
    row.querySelector('[data-cell="stake"]').textContent = `KSh ${Number(entry.stake).toFixed(0)}`;

    const result = row.querySelector('[data-cell="result"]');
    result.classList.remove('text-win', 'text-loss', 'text-casino-white/50');

    if (entry.status === 'won') {
        result.classList.add('text-win');
        result.textContent = `Won KSh ${Number(entry.payout).toFixed(0)} @ ${Number(entry.cashout_multiplier).toFixed(2)}x`;
    } else if (entry.status === 'lost') {
        result.classList.add('text-loss');
        result.textContent = 'Lost';
    } else {
        result.classList.add('text-casino-white/50');
        result.textContent = 'In play';
    }
}

/**
 * Clamps the current page into range and refreshes the Previous/Next
 * buttons and page label. Cheap enough to call after every change to
 * `state.activePlayers`, whether or not that change touches the page
 * currently on screen.
 */
function updateActivePlayersPagination() {
    const totalPages = totalActivePlayersPages();
    state.activePlayersPage = Math.min(state.activePlayersPage, totalPages - 1);

    el.activePlayersPageLabel.textContent = `Page ${state.activePlayersPage + 1} of ${totalPages}`;
    el.activePlayersPrevBtn.disabled = state.activePlayersPage === 0;
    el.activePlayersNextBtn.disabled = state.activePlayersPage >= totalPages - 1;
}

/**
 * Fully redraws whichever page is currently selected — used when the round
 * resets, the player changes pages, or an initial/resync snapshot lands.
 * Every row on the page gets the pop-in flourish here, which reads as
 * "this page just loaded" rather than the "everyone arrived at once"
 * effect a full-table rebuild caused before bets were paginated.
 */
function renderActivePlayersPage() {
    updateActivePlayersPagination();

    const start = state.activePlayersPage * ACTIVE_PLAYERS_PER_PAGE;
    const entries = orderedActivePlayers().slice(start, start + ACTIVE_PLAYERS_PER_PAGE);

    el.activePlayersBody.innerHTML = '';

    if (entries.length === 0) {
        el.activePlayersBody.innerHTML = '<tr id="active-players-empty"><td colspan="3" class="py-2 text-casino-white/40">No bets yet this round.</td></tr>';

        return;
    }

    entries.forEach((entry) => {
        const row = buildActivePlayerRow();
        row.classList.add('aviator-row-pop');
        fillActivePlayerRow(row, entry);
        el.activePlayersBody.appendChild(row);
    });
}

/**
 * Applies one bet's update without touching the rest of the table — used
 * for live feed.bet_placed/feed.bet_cashed_out/round.results events, so a
 * single bet arriving doesn't replay the pop-in animation for every row
 * already on screen (the actual cause of bots seeming to "appear all at
 * once" before pagination and per-row updates existed). Only touches the
 * DOM when this bet's page happens to be the one currently on screen —
 * otherwise it'll render correctly whenever the player pages to it.
 */
function upsertActivePlayer(entry) {
    state.activePlayers.set(entry.betId, entry);

    const index = Array.from(state.activePlayers.keys()).indexOf(entry.betId);
    const page = Math.floor(index / ACTIVE_PLAYERS_PER_PAGE);

    updateActivePlayersPagination();

    if (page !== state.activePlayersPage) {
        return;
    }

    let row = el.activePlayersBody.querySelector(`tr[data-bet-id="${entry.betId}"]`);

    if (!row) {
        document.getElementById('active-players-empty')?.remove();
        row = buildActivePlayerRow();
        row.classList.add('aviator-row-pop');
        el.activePlayersBody.appendChild(row);
    }

    fillActivePlayerRow(row, entry);
}

/**
 * Empties the "Active players this round" roster and resets to page one —
 * only ever called on round.betting_opened, when the round the table
 * describes has genuinely ended and a new one is starting.
 */
function clearActivePlayers() {
    state.activePlayers.clear();
    state.activePlayersPage = 0;
    renderActivePlayersPage();
}

function showAuthScreen() {
    el.authScreen.style.display = '';
    el.gameScreen.style.display = 'none';
}

function showGameScreen() {
    el.authScreen.style.display = 'none';
    // Cleared rather than set explicitly — game-screen's own Tailwind
    // classes decide block vs. `lg:flex`, this only decides whether it's
    // shown at all.
    el.gameScreen.style.display = '';
}

// --- Account panel (phone/mute/sign-out + wallet top-up/cash-out) ---

function openAccountPanel() {
    el.accountPanelOverlay.style.display = 'flex';
    // Force a layout pass so the browser paints the closed (off-screen)
    // transform first — adding the --open class in the same frame would
    // skip straight to open with no visible slide.
    void el.accountPanel.offsetHeight;
    el.accountPanel.classList.add('account-panel--open');
    setWalletTab('topup');
}

function closeAccountPanel() {
    el.accountPanel.classList.remove('account-panel--open');
    el.walletModalMessage.classList.add('hidden');

    // Only actually hide (remove from layout/interaction) once the slide-out
    // transition has had time to finish — matches aviator.css's duration.
    window.setTimeout(() => {
        if (!el.accountPanel.classList.contains('account-panel--open')) {
            el.accountPanelOverlay.style.display = 'none';
        }
    }, 300);
}

function setWalletTab(tab) {
    state.walletTab = tab;

    document.querySelectorAll('[data-wallet-tab]').forEach((btn) => {
        const active = btn.dataset.walletTab === tab;
        btn.classList.toggle('bg-gold', active);
        btn.classList.toggle('text-casino-black', active);
        btn.classList.toggle('text-casino-white/60', !active);
    });

    document.querySelectorAll('[data-wallet-panel]').forEach((panel) => {
        panel.classList.toggle('hidden', panel.dataset.walletPanel !== tab);
    });

    el.walletModalSubmitLabel.textContent = tab === 'topup' ? 'Pay with M-Pesa' : 'Withdraw to M-Pesa';
    el.walletModalMessage.classList.add('hidden');
}

async function submitWalletAction() {
    const tab = state.walletTab;
    const amountInput = document.getElementById(tab === 'topup' ? 'topup-amount' : 'withdraw-amount');

    el.walletModalSubmitBtn.disabled = true;

    try {
        await api(`wallet/${tab}`, {
            method: 'POST',
            body: JSON.stringify({ amount: Number(amountInput.value) }),
        });
    } catch (error) {
        el.walletModalMessageText.textContent = error.message;
        el.walletModalMessage.classList.remove('hidden');
        el.walletModalMessage.classList.add('flex');
    } finally {
        el.walletModalSubmitBtn.disabled = false;
    }
}

// --- Event wiring ---

el.loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    setError(el.loginError, el.loginErrorText, null);

    try {
        await login(el.loginMsisdn.value.trim(), el.loginPin.value.trim());
    } catch (error) {
        setError(el.loginError, el.loginErrorText, error.message);
    }
});

el.logoutBtn.addEventListener('click', () => logout());
el.placeBetBtn.addEventListener('click', () => placeBet());
el.cashoutBtn.addEventListener('click', () => cashOut());
el.soundToggleBtn.addEventListener('click', () => toggleSound());

document.querySelectorAll('[data-stake-chip]').forEach((chip) => {
    chip.addEventListener('click', () => {
        el.stakeInput.value = chip.dataset.stakeChip;
    });
});

el.accountFab.addEventListener('click', () => openAccountPanel());
el.accountPanelCloseBtn.addEventListener('click', () => closeAccountPanel());
el.accountPanelBackdrop.addEventListener('click', () => closeAccountPanel());
el.walletModalSubmitBtn.addEventListener('click', () => submitWalletAction());

document.querySelectorAll('[data-wallet-tab]').forEach((btn) => {
    btn.addEventListener('click', () => setWalletTab(btn.dataset.walletTab));
});

el.activePlayersPrevBtn.addEventListener('click', () => {
    state.activePlayersPage = Math.max(0, state.activePlayersPage - 1);
    renderActivePlayersPage();
});

el.activePlayersNextBtn.addEventListener('click', () => {
    state.activePlayersPage = Math.min(totalActivePlayersPages() - 1, state.activePlayersPage + 1);
    renderActivePlayersPage();
});

document.querySelectorAll('[data-amount-chip]').forEach((chip) => {
    chip.addEventListener('click', () => {
        const targetId = chip.dataset.amountChip === 'topup' ? 'topup-amount' : 'withdraw-amount';
        document.getElementById(targetId).value = chip.dataset.amount;
    });
});

(async function boot() {
    flightChart.reset();
    renderSoundToggle();

    if (!state.token) {
        showAuthScreen();

        return;
    }

    try {
        await refreshPlayer();
        await enterGame();
    } catch {
        // Stored token is stale/expired.
        localStorage.removeItem(TOKEN_STORAGE_KEY);
        state.token = null;
        showAuthScreen();
    }
})();
