<?php

namespace App\Http\Controllers;

use App\Mail\CongratulatoryEmail;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use Dompdf\Dompdf;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PDF;


class GameController extends Controller
{
    public function index()
    {
        $answeredQuestions = Session::get('answered_questions', []);

        $questionCount = Question::count(); // Get the total number of questions in the database

        $unansweredQuestionCount = $questionCount - count($answeredQuestions); // Calculate the number of unanswered questions

        $question = Question::whereNotIn('id', $answeredQuestions)->inRandomOrder()->first();

        if (!$question) {
            return Redirect::route('game.end');
        }

        return view('game.index', compact('question', 'unansweredQuestionCount', 'questionCount'));
    }

    public function checkAnswer(Request $request)
    {
        if ($request->has('stop_game')) {
            return redirect()->route('dashboard');
        }

        $question = Question::findOrFail($request->question_id);

        if ($question->correct_option == $request->selected_option) {
            $notification = 'Correct answer!';
            $isCorrect = true;
        } else {
            $notification = 'Wrong answer!';
            $isCorrect = false;
        }

        $user = User::findOrFail($request->user_id);
        if ($isCorrect) {
            $user->increment('score', 1);
            $user->increment('correct_answers');
        } else {
            $user->decrement('score', 1);
            $user->increment('wrong_answers');
        }

        $answeredQuestions = Session::get('answered_questions', []);
        $answeredQuestions[] = $question->id;
        Session::put('answered_questions', $answeredQuestions);

        usleep(1000000);

        return Redirect::route('game.index')->with([
            'notification' => $notification,
            'isCorrect' => $isCorrect,
            'correctOption' => $question->correct_option,
        ]);
    }

    public function end()
    {
        $user = User::findOrFail(auth()->id());
        $score = $user->score;
        $rank = User::where('score', '>', $score)->count();

        // Clear the user's score and answer statistics
        $user->update([
            'score' => 0,
            'correct_answers' => 0,
            'wrong_answers' => 0,
        ]);

        // Get the rank of the authenticated user
        $rank = User::where('score', '>', $score)->count();

        // Clear the answered questions from the session
        Session::forget('answered_questions');

        return view('game.end', compact('user', 'score', 'rank'));
    }

    public function playAgain()
    {
        $user = User::findOrFail(auth()->id());

        // Clear the user's correct_answers, wrong_answers, and score
        $user->correct_answers = 0;
        $user->wrong_answers = 0;
        $user->score = 0;
        $user->save();

        // Clear the answered questions from the session
        Session::forget('answered_questions');

        return redirect()->route('game.index');
    }

    public function shareAchievement()
    {
        $user = auth()->user();
        $score = $user->score;
    
        $users = User::orderByDesc('correct_answers')
            ->orderBy('wrong_answers')
            ->get();
    
        $rank = $users->search(function ($item) use ($user) {
            return $item->id === $user->id;
        });
    
        // Generate the certificate image using Intervention Image library
        $certificate = Image::make(public_path('path/to/template/certificate_template.jpg'));
    
        // Customize the certificate with user's name, score, and rank
        $certificate->text($user->name, x, y, function ($font) {
            // Configure font properties (size, color, etc.)
        });
    
        $certificate->text($score, x, y, function ($font) {
            // Configure font properties
        });
    
        $certificate->text($rank + 1, x, y, function ($font) {
            // Configure font properties
        });
    
        // Save the customized certificate as a JPG file
        $certificatePath = public_path('path/to/save/certificates/') . $user->id . '.jpg';
        $certificate->save($certificatePath);
    
        // Download the certificate file
        return response()->download($certificatePath)->deleteFileAfterSend(true);
    }

        public function downloadCertificate()
    {
        $user = auth()->user();
        $score = $user->score;

        // Retrieve all users sorted by score
        $users = User::orderByDesc('score')->get();

        // Calculate the user's rank
        $rank = $users->search(function ($item) use ($user) {
            return $item->id === $user->id;
        });

        // Generate the certificate PDF using Dompdf or SnappyPdf
        // Replace the placeholder variables with the actual user's data
        $certificateHtml = view('game.certificate-pdf', compact('user', 'score', 'rank'))->render();

        // Generate the PDF file using Dompdf or SnappyPdf
        $pdf = \PDF::loadHTML($certificateHtml); // Replace with your PDF generation library

        // Set the file name for the downloaded PDF
        $fileName = 'Certificate of Achievement - ' . $user->name . '.pdf';

        // Download the PDF file
        return $pdf->download($fileName);
    }

}


