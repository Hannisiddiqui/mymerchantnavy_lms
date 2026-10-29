<?php
require 'E:/MMP/backend/vendor/autoload.php';
$app = require 'E:/MMP/backend/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$B = 'C:/Users/Abrar/Desktop/Merchant Navy Exam/GP Rating Final/Maths/';
$files = ['prob' => '23. Probability/Practice_Test_100_Probability.pdf', 'quad' => '24. Quadratic Equation/Practice_Test -Quadratic_Equations.pdf', 'ap' => '25. Arithmatic Progression/Practice_Test-Arithmetic_Progression.pdf'];
foreach ($files as $k => $f) {
  $r = app(App\Services\Imports\PdfRuleParser::class)->parse($B.$f);
  $qs = $r->questions;
  file_put_contents(__DIR__."/parsed_$k.json", json_encode($qs, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
  $bad = 0; foreach ($qs as $q) { if (collect([$q['option_1'],$q['option_2'],$q['option_3'],$q['option_4']])->contains(fn($x)=>trim((string)$x)==='') || ($q['correct'] ?? null)===null || trim((string)($q['explanation']??''))==='') $bad++; }
  echo "$k: ".count($qs)." questions, $bad incomplete, keys: ".implode(',', array_keys($qs[0]))."\n";
  echo "  warnings: ".json_encode($r->warnings ?? null, JSON_UNESCAPED_UNICODE)."\n";
}
