<?php

namespace App\Mail\Join;

use App\Models\JoinSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

abstract class BaseJoinMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The "reply to" recipients of the message.
     *
     * @var array
     */
    public $replyTo = [
        [
            'name' => 'Bestuur Gumbo Millennium',
            'address' => 'bestuur@gumbo-millennium.nl'
        ]
    ];

    /**
     * Registry submission
     *
     * @var JoinSubmission
     */
    public $submission;

    /**
     * Create a new message instance.
     *
     * @param JoinSubmission $submission Submission to send
     * @return void
     */
    public function __construct(JoinSubmission $submission)
    {
        $this->submission = $submission;
        $this->subject = $this->createSubject($submission);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    abstract public function build();

    /**
     * Returns the subject
     *
     * @param JoinSubmission $submission
     * @return string
     */
    abstract protected function createSubject(JoinSubmission $submission) : string;
}
