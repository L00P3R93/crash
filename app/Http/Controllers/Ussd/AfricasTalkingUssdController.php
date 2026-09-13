<?php

namespace App\Http\Controllers\Ussd;

use App\Domain\Ussd\UssdMenuRenderer;
use App\Domain\Ussd\UssdSessionManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AfricasTalkingUssdController extends Controller
{
    public function __construct(
        private readonly UssdSessionManager $sessions,
        private readonly UssdMenuRenderer $renderer,
    ) {}

    public function handle(Request $request): Response
    {
        $session = $this->sessions->loadOrCreate(
            sessionId: (string) $request->input('sessionId'),
            msisdn: (string) $request->input('phoneNumber'),
            serviceCode: (string) $request->input('serviceCode'),
            networkCode: $request->input('networkCode'),
        );

        $text = (string) $request->input('text', '');
        $input = $this->sessions->extractLastInput($text);

        $response = $this->renderer->resolveScreen($session)->handle($session, $input);

        if ($response->endSession) {
            $this->sessions->endSession($session, $input);
        } else {
            $this->sessions->persist($session, $input);
        }

        $prefix = $response->endSession ? 'END' : 'CON';

        return response("{$prefix} {$response->body}", 200)
            ->header('Content-Type', 'text/plain');
    }
}
