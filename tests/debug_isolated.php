<?php

$file = sys_get_temp_dir() . '/mock_wc_variations_phase6.json';
if (file_exists($file)) {
    unlink($file);
    echo "Removed mock variations temp file. Fresh fixtures will load.\n";
} else {
    echo "No temp file found.\n";
}
