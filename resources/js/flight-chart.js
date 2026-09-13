// Shared "Dawn Ascent" flight-path drawing math — used by both the real
// play screen (resources/js/play.js) and the welcome page's scripted demo
// (resources/js/flight-canvas-demo.js), so the two never drift apart
// visually. Purely decorative: never consulted for any actual game logic.

const CHART_WIDTH = 300;
const CHART_HEIGHT = 150;
const CHART_PX_PER_SECOND = 34;
const CHART_LOG_SCALE = 42;

export function createFlightChart(pathEl, fillEl) {
    let points = [];

    function point(elapsedSeconds, multiplier) {
        const x = Math.min(CHART_WIDTH + 20, elapsedSeconds * CHART_PX_PER_SECOND);
        const y = Math.max(6, CHART_HEIGHT - Math.log(multiplier) * CHART_LOG_SCALE);

        return { x, y };
    }

    return {
        draw(elapsedSeconds, multiplier) {
            points.push(point(elapsedSeconds, multiplier));

            const linePath = points.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x},${p.y}`).join(' ');
            pathEl.setAttribute('d', linePath);

            const last = points[points.length - 1];
            fillEl.setAttribute('d', `${linePath} L${last.x},${CHART_HEIGHT} L${points[0]?.x ?? 0},${CHART_HEIGHT} Z`);
        },
        reset() {
            points = [];
            pathEl.setAttribute('d', '');
            fillEl.setAttribute('d', '');
        },
    };
}
