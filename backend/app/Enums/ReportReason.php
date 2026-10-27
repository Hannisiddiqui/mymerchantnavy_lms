<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** What a student says is wrong with a question. Same keys as `ReportReason` in student-app/src/types/index.ts. */
enum ReportReason: string implements HasLabel
{
    case WrongAnswer = 'wrong_answer';
    case QuestionError = 'question_error';
    case OptionError = 'option_error';
    case ExplanationError = 'explanation_error';
    case Formatting = 'formatting';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::WrongAnswer => 'Answer key is wrong',
            self::QuestionError => 'Mistake in the question',
            self::OptionError => 'Mistake in the options',
            self::ExplanationError => 'Solution is wrong or unclear',
            self::Formatting => 'Typo or display problem',
            self::Other => 'Something else',
        };
    }
}
