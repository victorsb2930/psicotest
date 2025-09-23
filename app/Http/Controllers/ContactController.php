<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactMessage;

class ContactController extends Controller {
	public function send(Request $request) {
		$data = $request->validate([
			'name' => ['required','string','max:120'],
			'email' => ['required','email','max:180'],
			'subject' => ['required','string','max:180'],
			'message' => ['required','string','max:5000'],
		]);

		$to = env('CONTACT_TO', config('mail.from.address'));

		try {
			Mail::to($to)->send(new ContactMessage($data));
		} catch (\Throwable $e) {
			report($e);
			return response()->json(['message' => 'No se pudo enviar el mensaje. Inténtalo más tarde.'], 500);
		}

		return response()->json(['message' => 'Mensaje enviado correctamente.'], 200);
	}
}