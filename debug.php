<?php
// debug.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Temporarily increase memory limit for this script to rule it out
ini_set('memory_limit', '512M');

require_once 'api.php';

echo "Starting debug script...\n";
echo "This script will attempt to generate the full JSON payload and check for errors.\n";
echo "Initial memory usage: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
echo "Current memory limit: " . ini_get('memory_limit') . "\n\n";

// Use output buffering to capture the output of getAllContent()
ob_start();
getAllContent();
$jsonOutput = ob_get_clean();

echo "Finished generating content.\n";
echo "Peak memory usage: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB\n";
echo "Final memory usage: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n\n";

// Verify the captured output
json_decode($jsonOutput);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "SUCCESS: The captured output is VALID JSON.\n";
    echo "Length of JSON output: " . strlen($jsonOutput) . " bytes\n";
} else {
    echo "ERROR: The captured output is INVALID JSON.\n";
    echo "JSON Error Code: " . json_last_error() . "\n";
    echo "JSON Error Message: " . json_last_error_msg() . "\n";
}

echo "\nDebug script finished.\n";
?>