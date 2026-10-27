<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
foreach ([17,38,44,46] as $tid) {
  $ids = DB::table('test_questions')->where('test_id',$tid)->orderBy('position')->pluck('question_id');
  $qs = App\Models\Question::with('options')->whereIn('id',$ids)->get()->keyBy('id');
  $ans=[1=>0,2=>0,3=>0,4=>0]; $d=[]; $secs=[]; $seen=[]; $dup=0; $bad=0;
  foreach ($ids as $id) { $q=$qs[$id]; $c=$q->options->firstWhere('is_correct',true); if(!$c||$q->options->where('is_correct',true)->count()!=1||$q->options->count()!=4)$bad++; else $ans[$c->position]++;
    $d[$q->difficulty]=($d[$q->difficulty]??0)+1;
    if(preg_match('/Revise [Ss]ection (\d+\.\d+)/',$q->explanation,$m))$secs[]=$m[1];
    $k=strip_tags($q->body).'|'.$q->options->sortBy('position')->pluck('body')->implode('|'); if(isset($seen[$k]))$dup++; $seen[$k]=1; }
  $t=App\Models\Test::find($tid);
  echo "test $tid n=".count($ids)." ans=".json_encode($ans)." diff=".json_encode($d)." bad=$bad dup=$dup marks={$t->marks_correct}/{$t->marks_wrong} dur={$t->duration_minutes}\n";
  $out=[];$prev=-1;$inv=0; foreach($secs as $s){ if((float)$s<$prev)$inv++; $prev=(float)$s;} echo "  section order inversions: $inv\n";
}
