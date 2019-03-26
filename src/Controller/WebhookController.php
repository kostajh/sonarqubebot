<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookController {

	public static function index( Request $request, LoggerInterface $logger ) {
		// todo validate request
		// todo post to gerrit
		$logger->info( $request->getContent( ) );
		return new Response();
	}
}
