<?php

namespace App\Http\Controllers\Join;

use App\Http\Controllers\Controller;
use App\Http\Requests\JoinRequest;
use App\Mail\Join\BoardJoinMail;
use App\Mail\Join\FullJoinMail;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

/**
 * Handles sign ups to the student community. Presents a whole form and
 * isn't very user-friendly.
 *
 * @author Roelof Roos <github@roelof.io>
 * @license MPL-2.0
 */
class FullController extends Controller
{
    use BuildsJoinSubmissions;

    /**
     * E-mail address and name of the board
     */
    const TO_BOARD = [[
        'name' => 'Bestuur Gumbo Millennium',
        'email' => 'bestuur@gumbo-millennium.nl',
    ]];

    /**
     * Shows the sign-up form
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        // Show form
        return view('main.join.full')->with([
            'page' => Page::slug('join')->first(),
            'user' => $request->user()
        ]);
    }

    /**
     * Handldes the user registration
     *
     * @param SignUpRequest $request
     * @return Response
     */
    public function submit(JoinRequest $request)
    {
        // Get name and e-mail address
        $email = $request->get('email');
        $name = collect($request->only(['first_name', 'insert', 'last_name']))
            ->reject('empty')
            ->implode(' ');

        // Sends the e-mails
        $submission = $this->buildJoinSubmission($request);

        if (!$submission) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors('Er is iets fout gegaan bij het aanmelden.');
        }

        // Send mail to user
        Mail::to([compact('name', 'email')])
            ->send(new FullJoinMail($submission));

        // Send mail to board
        Mail::to(self::TO_BOARD)
            ->send(new BoardJoinMail($submission));

        // Send redirect reploy
        return redirect()
            ->route('join.complete')
            ->with('submission', $submission);
    }
}
