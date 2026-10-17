<?php
// Backward compatibility for existing installations of nik-voicedesk/nik-voicedesk.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'NIKVOTIA_VERSION' ) ) {
	require_once __DIR__ . '/nik-voice-ticketing-ai.php';
}
