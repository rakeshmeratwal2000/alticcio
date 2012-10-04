<?php
 DEFINE('LOGLABELS_PLAIN',0); DEFINE('LOGLABELS_MAGNITUDE',1); class LogScale extends LinearScale { function __construct($min,$max,$type="y") { parent::__construct($min,$max,$type); $this->ticks = new LogTicks(); $this->name = 'log'; } function Translate($a) { if( !is_numeric($a) ) { if( $a != '' && $a != '-' && $a != 'x' ) { JpGraphError::RaiseL(11001); } return 1; } if( $a < 0 ) { JpGraphError::RaiseL(11002); exit(1); } if( $a==0 ) $a=1; $a=log10($a); return ceil($this->off + ($a*1.0 - $this->scale[0]) * $this->scale_factor); } function RelTranslate($a) { if( !is_numeric($a) ) { if( $a != '' && $a != '-' && $a != 'x' ) { JpGraphError::RaiseL(11001); } return 1; } if( $a==0 ) { $a=1; } $a=log10($a); return round(($a*1.0 - $this->scale[0]) * $this->scale_factor); } function GetMinVal() { if( function_exists("bcpow") ) { return round(bcpow(10,$this->scale[0],15),14); } else { return round(pow(10,$this->scale[0]),14); } } function GetMaxVal() { if( function_exists("bcpow") ) { return round(bcpow(10,$this->scale[1],15),14); } else { return round(pow(10,$this->scale[1]),14); } } function AutoScale($img,$min,$max,$maxsteps,$majend=true) { if( $min==0 ) $min=1; if( $max <= 0 ) { JpGraphError::RaiseL(11004); } if( is_numeric($this->autoscale_min) ) { $smin = round($this->autoscale_min); $smax = ceil(log10($max)); if( $min >= $max ) { JpGraphError::RaiseL(25071); } } else { $smin = floor(log10($min)); if( is_numeric($this->autoscale_max) ) { $smax = round($this->autoscale_max); if( $smin >= $smax ) { JpGraphError::RaiseL(25072); } } else $smax = ceil(log10($max)); } $this->Update($img,$smin,$smax); } } class LogTicks extends Ticks{ private $label_logtype=LOGLABELS_MAGNITUDE; private $ticklabels_pos = array(); function LogTicks() { } function IsSpecified() { return true; } function SetLabelLogType($aType) { $this->label_logtype = $aType; } function GetMajor() { return -1; } function SetTextLabelStart($aStart) { JpGraphError::RaiseL(11005); } function SetXLabelOffset($dummy) { } function Stroke($img,$scale,$pos) { $start = $scale->GetMinVal(); $limit = $scale->GetMaxVal(); $nextMajor = 10*$start; $step = $nextMajor / 10.0; $img->SetLineWeight($this->weight); if( $scale->type == "y" ) { $a=$pos + $this->direction*$this->GetMinTickAbsSize(); $a2=$pos + $this->direction*$this->GetMajTickAbsSize(); $count=1; $this->maj_ticks_pos[0]=$scale->Translate($start); $this->maj_ticklabels_pos[0]=$scale->Translate($start); if( $this->supress_first ) $this->maj_ticks_label[0]=""; else { if( $this->label_formfunc != '' ) { $f = $this->label_formfunc; $this->maj_ticks_label[0]=call_user_func($f,$start); } elseif( $this->label_logtype == LOGLABELS_PLAIN ) { $this->maj_ticks_label[0]=$start; } else { $this->maj_ticks_label[0]='10^'.round(log10($start)); } } $i=1; for($y=$start; $y<=$limit; $y+=$step,++$count ) { $ys=$scale->Translate($y); $this->ticks_pos[]=$ys; $this->ticklabels_pos[]=$ys; if( $count % 10 == 0 ) { if( !$this->supress_tickmarks ) { if( $this->majcolor!="" ) { $img->PushColor($this->majcolor); $img->Line($pos,$ys,$a2,$ys); $img->PopColor(); } else { $img->Line($pos,$ys,$a2,$ys); } } $this->maj_ticks_pos[$i]=$ys; $this->maj_ticklabels_pos[$i]=$ys; if( $this->label_formfunc != '' ) { $f = $this->label_formfunc; $this->maj_ticks_label[$i]=call_user_func($f,$nextMajor); } elseif( $this->label_logtype == 0 ) { $this->maj_ticks_label[$i]=$nextMajor; } else { $this->maj_ticks_label[$i]='10^'.round(log10($nextMajor)); } ++$i; $nextMajor *= 10; $step *= 10; $count=1; } else { if( !$this->supress_tickmarks && !$this->supress_minor_tickmarks) { if( $this->mincolor!="" ) { $img->PushColor($this->mincolor); } $img->Line($pos,$ys,$a,$ys); if( $this->mincolor!="" ) { $img->PopColor(); } } } } } else { $a=$pos - $this->direction*$this->GetMinTickAbsSize(); $a2=$pos - $this->direction*$this->GetMajTickAbsSize(); $count=1; $this->maj_ticks_pos[0]=$scale->Translate($start); $this->maj_ticklabels_pos[0]=$scale->Translate($start); if( $this->supress_first ) { $this->maj_ticks_label[0]=""; } else { if( $this->label_formfunc != '' ) { $f = $this->label_formfunc; $this->maj_ticks_label[0]=call_user_func($f,$start); } elseif( $this->label_logtype == 0 ) { $this->maj_ticks_label[0]=$start; } else { $this->maj_ticks_label[0]='10^'.round(log10($start)); } } $i=1; for($x=$start; $x<=$limit; $x+=$step,++$count ) { $xs=$scale->Translate($x); $this->ticks_pos[]=$xs; $this->ticklabels_pos[]=$xs; if( $count % 10 == 0 ) { if( !$this->supress_tickmarks ) { $img->Line($xs,$pos,$xs,$a2); } $this->maj_ticks_pos[$i]=$xs; $this->maj_ticklabels_pos[$i]=$xs; if( $this->label_formfunc != '' ) { $f = $this->label_formfunc; $this->maj_ticks_label[$i]=call_user_func($f,$nextMajor); } elseif( $this->label_logtype == 0 ) { $this->maj_ticks_label[$i]=$nextMajor; } else { $this->maj_ticks_label[$i]='10^'.round(log10($nextMajor)); } ++$i; $nextMajor *= 10; $step *= 10; $count=1; } else { if( !$this->supress_tickmarks && !$this->supress_minor_tickmarks) { $img->Line($xs,$pos,$xs,$a); } } } } return true; } } ?>
