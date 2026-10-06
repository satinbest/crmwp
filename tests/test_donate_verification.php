<?php

declare(strict_types=1);

/**
 * Verification Test: Donate Configuration & Bank Card Implementation
 */

echo "========================================================\n";
echo "   DONATE REDESIGN & SOURCE OF TRUTH VERIFICATION\n";
echo "========================================================\n";

$pass = 0;
$fail = 0;

function assertCondition(bool $cond, string $msg): void {
    global $pass, $fail;
    if ($cond) {
        echo " [PASS] {$msg}\n";
        $pass++;
    } else {
        echo " [FAIL] {$msg}\n";
        $fail++;
    }
}

// 1. Check resources/js/config/donation.ts exists and content
$tsFile = __DIR__ . '/../resources/js/config/donation.ts';
assertCondition(file_exists($tsFile), "donation.ts exists in resources/js/config/");
if (file_exists($tsFile)) {
    $content = file_get_contents($tsFile);
    assertCondition(strpos($content, "'حسین محمدپور'") !== false, "donation.ts contains recipientName 'حسین محمدپور'");
    assertCondition(strpos($content, "'6219861931965403'") !== false, "donation.ts contains cardNumber '6219861931965403'");
    assertCondition(strpos($content, "'info@hosseinmohammadpour.ir'") !== false, "donation.ts contains email 'info@hosseinmohammadpour.ir'");
    assertCondition(strpos($content, "'satinbest/crmwp'") !== false, "donation.ts contains github 'satinbest/crmwp'");
}

// 2. Check DonateButton.vue
$vueFile = __DIR__ . '/../resources/js/components/ui/DonateButton.vue';
assertCondition(file_exists($vueFile), "DonateButton.vue exists");
if (file_exists($vueFile)) {
    $vueContent = file_get_contents($vueFile);
    assertCondition(strpos($vueContent, "import { donationConfig } from '@/config/donation'") !== false, "DonateButton imports donationConfig");
    assertCondition(strpos($vueContent, "formattedCardPersian") !== false, "DonateButton has formattedCardPersian computed property");
    assertCondition(strpos($vueContent, "copyCardNumber") !== false, "DonateButton has copyCardNumber function");
    assertCondition(strpos($vueContent, "font-mono") === false, "DonateButton does NOT use font-mono");
    assertCondition(strpos($vueContent, "linear-gradient(135deg, #FEF7CD") !== false, "DonateButton uses warm yellow/gold bank card gradient");
}

// 3. Check config/app.php
$appConfig = require __DIR__ . '/../config/app.php';
assertCondition(isset($appConfig['donate']), "config/app.php has 'donate' section");
assertCondition(($appConfig['donate']['recipient_name'] ?? '') === 'حسین محمدپور', "config/app.php default recipient_name is 'حسین محمدپور'");
assertCondition(($appConfig['donate']['card_number'] ?? '') === '6219861931965403', "config/app.php default card_number is '6219861931965403'");

// 4. Check production built bundle
$jsFiles = glob(__DIR__ . '/../public/assets/index-*.js');
assertCondition(!empty($jsFiles), "Production compiled index bundle exists in public/assets");
if (!empty($jsFiles)) {
    $bundleContent = file_get_contents($jsFiles[0]);
    assertCondition(strpos($bundleContent, '6219861931965403') !== false, "Production bundle contains cardNumber 6219861931965403");
    assertCondition(strpos($bundleContent, 'حسین محمدپور') !== false, "Production bundle contains recipientName حسین محمدپور");
    assertCondition(strpos($bundleContent, 'info@hosseinmohammadpour.ir') !== false, "Production bundle contains email");
}

echo "========================================================\n";
echo " Results: {$pass} passed, {$fail} failed.\n";
echo "========================================================\n";

exit($fail > 0 ? 1 : 0);
