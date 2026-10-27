<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Test; use App\Models\Question; use App\Services\Tests\QuestionService;
use Illuminate\Support\Facades\DB;
$apply = ($argv[1] ?? '') === 'apply';
$svc = app(QuestionService::class);
foreach ($argv as $i => $f) { if ($i < 2 || !is_file($f)) continue;
  $j = json_decode(file_get_contents($f), true);
  $t = Test::findOrFail($j['test']);
  $rows = DB::table('test_questions')->where('test_id', $t->id)->orderBy('position')->get();
  $qs = Question::with('options')->whereIn('id', $rows->pluck('question_id'))->get()->keyBy('id');
  $first = $qs[$rows[0]->question_id];
  $items = []; $last = 0.0;
  foreach ($rows as $n => $r) {
    $q = $qs[$r->question_id];
    if (preg_match('/Revise [Ss]ection (\d+\.\d+)/', (string)$q->explanation, $m)) $last = (float)$m[1];
    $items[] = ['id' => $q->id, 'sec' => $last, 'sub' => $n, 'new' => false];
  }
  $stems = []; foreach ($qs as $q) $stems[trim(strip_tags($q->body)).'|'.implode('|', $q->options->sortBy('position')->pluck('body')->map(fn($b)=>trim(html_entity_decode(strip_tags($b))))->all())] = 1;
  $ids = [];
  foreach ($j['questions'] as $n => $f2) {
    if (isset($stems[trim(strip_tags($f2['body'])).'|'.implode('|', array_map(fn($k)=>trim(html_entity_decode(strip_tags($f2["option_$k"]))), [1,2,3,4]))])) { echo "DUPLICATE STEM skipped: ".strip_tags($f2['body'])."\n"; continue; }
    $form = $f2 + ['subject_id' => $first->subject_id, 'chapter_id' => $first->chapter_id];
    if ($apply) { $q = $svc->save($form); $id = $q->id; } else { $id = -($n+1); }
    $ids[] = [$id, $f2['order'] ?? null];
  }
  if (isset($j['insert_at'])) {
    $list = array_column($items, 'id'); array_splice($list, $j['insert_at'], 0, array_column($ids, 0));
  } else {
    foreach ($ids as $k => [$id, $o]) $items[] = ['id' => $id, 'sec' => (float)$o[0], 'sub' => 100000 + $o[1], 'new' => true];
    usort($items, fn($a, $b) => [$a['sec'], $a['sub']] <=> [$b['sec'], $b['sub']]);
    $list = array_column($items, 'id');
  }
  echo "test {$t->id} ({$t->title}): had ".count($rows).", adding ".count($ids).", will have ".count($list)."\n";
  if ($apply) {
    DB::transaction(function () use ($t, $list) {
      DB::table('test_questions')->where('test_id', $t->id)->delete();
      foreach ($list as $p => $id) DB::table('test_questions')->insert(['test_id' => $t->id, 'question_id' => $id, 'position' => $p]);
    });
    $t->update(['marks_correct' => 2, 'marks_wrong' => 0.25, 'duration_minutes' => 90]);
    echo "  now ".DB::table('test_questions')->where('test_id', $t->id)->count()." questions\n";
  }
}
