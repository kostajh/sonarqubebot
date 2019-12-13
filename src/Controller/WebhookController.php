<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookController {

	private const FEEDBACK_URL =
		'https://www.mediawiki.org/wiki/Talk:Continuous_integration/Codehealth_Pipeline';

	/**
	 * @param Request $request
	 * @param LoggerInterface $logger
	 * @return RedirectResponse|Response
	 * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
	 * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
	 * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
	 * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
	 */
	public static function index( Request $request, LoggerInterface $logger ) {
		if ( $request->isMethod( 'GET' ) ) {
			return new RedirectResponse(
				'https://www.mediawiki.org/wiki/Continuous_integration/Codehealth_Pipeline'
			);
		}

		$logger->debug( $request->getContent() );

		$hmac = $request->headers->get( 'X-Sonar-Webhook-HMAC-SHA256' );
		$expected_hmac = hash_hmac( 'sha256', $request->getContent(), $_SERVER['SONARQUBE_HMAC'] );
		if ( !$hmac || $hmac !== $expected_hmac ) {
			$logger->error( 'HMAC validation error.' );
			return new Response();
		}
		$analysisJson = json_decode( $request->getContent(), true );
		if ( $analysisJson['branch'] === 'master' ) {
			// Skip commenting on master for now.
			return new Response( 'No comment.' );
		}
		$passedQualityGate = $analysisJson['qualityGate']['status'] === 'SUCCESS';
		$successMessage = $passedQualityGate ?
			'✔ Quality gate passed!' :
			'❌ Quality gate failed';
		$detailsMessage = '';
		foreach ( $analysisJson['qualityGate']['conditions']  as $condition ) {
			$humanReadableMetric = trim( str_replace( 'new', '', str_replace( '_', ' ',
				$condition['metric'] ) ) );
			$humanReadableReason = $condition['value'] . ' is ' . strtolower( str_replace( '_',
					' ', $condition['operator'] ) ) . ' ' . $condition['errorThreshold'];
			$detailsMessage .= $condition['status'] === 'OK' ?
				"\n* ✔ " . $humanReadableMetric :
				"\n* ❌ " . $humanReadableMetric . ' (' . $humanReadableReason . ')';
		}
		$detailsMessage .= "\n\nReport: " . $analysisJson['branch']['url'];
		if ( !$passedQualityGate ) {
			$detailsMessage .= "\n\nThis patch can still be merged. " .
			   'Please give feedback and report false positives at ' . self::FEEDBACK_URL;
		}
		$gerritComment = $successMessage . "\n\n" . $detailsMessage;
		$client = HttpClient::createForBaseUri( 'https://gerrit.wikimedia.org/', [
			'auth_basic' => [ $_SERVER['GERRIT_USERNAME'], $_SERVER['GERRIT_HTTP_PASSWORD'] ]
		] );
		list( $gerritShortId, $gerritRevision ) = explode( '-', $analysisJson['branch']['name'] );
		$url = '/r/a/changes/' . urlencode( str_replace( '-', '/',
				$analysisJson['project']['key'] ) ) . '~' . $gerritShortId . '/revisions/' .
			   $gerritRevision . '/review';
		try {
			$response = $client->request( 'POST', $url, [
				'body' => [
					'message' => $gerritComment,
					'labels' => [
						'Code-Review' => $passedQualityGate ? 1 : 0
					]
				]
			] );
			$logger->info( $response->getStatusCode() . ' ' . $response->getContent() );
		} catch ( \Exception $exception ) {
			$logger->error( $exception->getMessage() );
		}
		return new Response();
	}
}
