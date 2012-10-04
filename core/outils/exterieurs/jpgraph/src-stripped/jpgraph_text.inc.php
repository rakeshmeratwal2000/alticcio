<?php
 class Text { public $t,$margin=0; public $x=0,$y=0,$halign="left",$valign="top",$color=array(0,0,0); public $hide=false, $dir=0; public $iScalePosY=null,$iScalePosX=null; public $iWordwrap=0; public $font_family=FF_FONT1,$font_style=FS_NORMAL,$font_size=12; protected $boxed=false; protected $paragraph_align="left"; protected $icornerradius=0,$ishadowwidth=3; protected $fcolor='white',$bcolor='black',$shadow=false; protected $iCSIMarea='',$iCSIMalt='',$iCSIMtarget='',$iCSIMWinTarget=''; private $iBoxType = 1; function __construct($aTxt="",$aXAbsPos=0,$aYAbsPos=0) { if( ! is_string($aTxt) ) { JpGraphError::RaiseL(25050); } $this->t = $aTxt; $this->x = round($aXAbsPos); $this->y = round($aYAbsPos); $this->margin = 0; } function Set($aTxt) { $this->t = $aTxt; } function SetPos($aXAbsPos=0,$aYAbsPos=0,$aHAlign="left",$aVAlign="top") { $this->x = $aXAbsPos; $this->y = $aYAbsPos; $this->halign = $aHAlign; $this->valign = $aVAlign; } function SetScalePos($aX,$aY) { $this->iScalePosX = $aX; $this->iScalePosY = $aY; } function Align($aHAlign,$aVAlign="top",$aParagraphAlign="") { $this->halign = $aHAlign; $this->valign = $aVAlign; if( $aParagraphAlign != "" ) $this->paragraph_align = $aParagraphAlign; } function SetAlign($aHAlign,$aVAlign="top",$aParagraphAlign="") { $this->Align($aHAlign,$aVAlign,$aParagraphAlign); } function ParagraphAlign($aAlign) { $this->paragraph_align = $aAlign; } function SetParagraphAlign($aAlign) { $this->paragraph_align = $aAlign; } function SetShadow($aShadowColor='gray',$aShadowWidth=3) { $this->ishadowwidth=$aShadowWidth; $this->shadow=$aShadowColor; $this->boxed=true; } function SetWordWrap($aCol) { $this->iWordwrap = $aCol ; } function SetBox($aFrameColor=array(255,255,255),$aBorderColor=array(0,0,0),$aShadowColor=false,$aCornerRadius=4,$aShadowWidth=3) { if( $aFrameColor === false ) { $this->boxed=false; } else { $this->boxed=true; } $this->fcolor=$aFrameColor; $this->bcolor=$aBorderColor; if( $aShadowColor === true ) { $aShadowColor = 'gray'; } $this->shadow=$aShadowColor; $this->icornerradius=$aCornerRadius; $this->ishadowwidth=$aShadowWidth; } function SetBox2($aFrameColor=array(255,255,255),$aBorderColor=array(0,0,0),$aShadowColor=false,$aCornerRadius=4,$aShadowWidth=3) { $this->iBoxType=2; $this->SetBox($aFrameColor,$aBorderColor,$aShadowColor,$aCornerRadius,$aShadowWidth); } function Hide($aHide=true) { $this->hide=$aHide; } function Show($aShow=true) { $this->hide=!$aShow; } function SetFont($aFamily,$aStyle=FS_NORMAL,$aSize=10) { $this->font_family=$aFamily; $this->font_style=$aStyle; $this->font_size=$aSize; } function Center($aLeft,$aRight,$aYAbsPos=false) { $this->x = $aLeft + ($aRight-$aLeft )/2; $this->halign = "center"; if( is_numeric($aYAbsPos) ) $this->y = $aYAbsPos; } function SetColor($aColor) { $this->color = $aColor; } function SetAngle($aAngle) { $this->SetOrientation($aAngle); } function SetOrientation($aDirection=0) { if( is_numeric($aDirection) ) $this->dir=$aDirection; elseif( $aDirection=="h" ) $this->dir = 0; elseif( $aDirection=="v" ) $this->dir = 90; else JpGraphError::RaiseL(25051); } function GetWidth($aImg) { $aImg->SetFont($this->font_family,$this->font_style,$this->font_size); $w = $aImg->GetTextWidth($this->t,$this->dir); return $w; } function GetFontHeight($aImg) { $aImg->SetFont($this->font_family,$this->font_style,$this->font_size); $h = $aImg->GetFontHeight(); return $h; } function GetTextHeight($aImg) { $aImg->SetFont($this->font_family,$this->font_style,$this->font_size); $h = $aImg->GetTextHeight($this->t,$this->dir); return $h; } function GetHeight($aImg) { $aImg->SetFont($this->font_family,$this->font_style,$this->font_size); $h = $aImg->GetTextHeight($this->t,$this->dir); return $h; } function SetMargin($aMarg) { $this->margin = $aMarg; } function StrokeWithScale($aImg,$axscale,$ayscale) { if( $this->iScalePosX === null || $this->iScalePosY === null ) { $this->Stroke($aImg); } else { $this->Stroke($aImg, round($axscale->Translate($this->iScalePosX)), round($ayscale->Translate($this->iScalePosY))); } } function SetCSIMTarget($aURITarget,$aAlt='',$aWinTarget='') { $this->iCSIMtarget = $aURITarget; $this->iCSIMalt = $aAlt; $this->iCSIMWinTarget = $aWinTarget; } function GetCSIMareas() { if( $this->iCSIMtarget !== '' ) { return $this->iCSIMarea; } else { return ''; } } function Stroke($aImg,$x=null,$y=null) { if( $x !== null ) $this->x = round($x); if( $y !== null ) $this->y = round($y); if( $this->iWordwrap > 0 ) { $this->t = wordwrap($this->t,$this->iWordwrap,"\n"); } if( $this->x < 1 && $this->x > 0 ) $this->x *= $aImg->width; if( $this->y < 1 && $this->y > 0 ) $this->y *= $aImg->height; $aImg->PushColor($this->color); $aImg->SetFont($this->font_family,$this->font_style,$this->font_size); $aImg->SetTextAlign($this->halign,$this->valign); if( $this->boxed ) { if( $this->fcolor=="nofill" ) { $this->fcolor=false; } $oldweight=$aImg->SetLineWeight(1); if( $this->iBoxType == 2 && $this->font_family > FF_FONT2+2 ) { $bbox = $aImg->StrokeBoxedText2($this->x, $this->y, $this->t, $this->dir, $this->fcolor, $this->bcolor, $this->shadow, $this->paragraph_align, 2,4, $this->icornerradius, $this->ishadowwidth); } else { $bbox = $aImg->StrokeBoxedText($this->x,$this->y,$this->t, $this->dir,$this->fcolor,$this->bcolor,$this->shadow, $this->paragraph_align,3,3,$this->icornerradius, $this->ishadowwidth); } $aImg->SetLineWeight($oldweight); } else { $debug=false; $bbox = $aImg->StrokeText($this->x,$this->y,$this->t,$this->dir,$this->paragraph_align,$debug); } $coords = $bbox[0].','.$bbox[1].','.$bbox[2].','.$bbox[3].','.$bbox[4].','.$bbox[5].','.$bbox[6].','.$bbox[7]; $this->iCSIMarea = "<area shape=\"poly\" coords=\"$coords\" href=\"".htmlentities($this->iCSIMtarget)."\" "; if( trim($this->iCSIMalt) != '' ) { $this->iCSIMarea .= " alt=\"".$this->iCSIMalt."\" "; $this->iCSIMarea .= " title=\"".$this->iCSIMalt."\" "; } if( trim($this->iCSIMWinTarget) != '' ) { $this->iCSIMarea .= " target=\"".$this->iCSIMWinTarget."\" "; } $this->iCSIMarea .= " />\n"; $aImg->PopColor($this->color); } } ?>
