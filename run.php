<?php
//error_reporting(0);

require 'config.php';

function similar($rgb1, $rgb2, $value = 10) {
	$r1 = ($rgb1 >> 16) & 0xFF;
	$g1 = ($rgb1 >> 8) & 0xFF;
	$b1 = $rgb1 & 0xFF;
	$r2 = ($rgb2 >> 16) & 0xFF;
	$g2 = ($rgb2 >> 8) & 0xFF;
	$b2 = $rgb2 & 0xFF;
	return abs($r1 - $r2) < $value && abs($b1 - $b2) < $value && abs($g1 - $g2) < $value;
}

function getStart() {
	global $image;
	$l_r    = 0;
	$cnt    = 0;
	$width  = imagesx($image);
	$height = imagesy($image);
	for ($i = $height / 3 * 2; $i > $height / 3; $i--) {
		for ($l = 0; $l < $width; $l++) {
			$c = imagecolorat($image, $l, $i);
			if (similar($c, 3750243, 20)) {
				$r = $l;
				while($r+1 < $width && similar(imagecolorat($image, $r+1, $i), 3750243, 20)){
					$r++;
				}
				if ($r - $l > BODY_WIDTH * 0.5){
					if ($r <= $l_r) {
						return [$i, round(($l + $r) / 2)];
					}
					else {
						$cnt = 0;
					}
					$l_r = $r;
				}
				$l = $r;
			}
		}
	}
	return array($x, $y);
}

function getEnd() {
	global $image;
	global $sx, $sy;
	$l_r    = 0;
	$cnt    = 0;
	$width  = imagesx($image);
	$height = imagesy($image);
	for ($i = $height / 3; $i < $sx; $i++) {
		$demo  = imagecolorat($image, $width - 1, $i);
		for ($l = 0; $l < $width; $l++) {
			$c = imagecolorat($image, $l, $i);
			if (!similar($c, $demo)) {
				$r = $l;
				while($r+1 < $width && !similar(imagecolorat($image, $r+1, $i), $demo)){
					$r++;
				}
				if (abs(($l + $r) / 2 - $sy) > BODY_WIDTH * 0.5) {
					if ($r - $l > BODY_WIDTH * 0.9){
                        if (!isset($mid)) $mid = ($l + $r) / 2;
						if ($r <= $l_r) {
							$cnt ++;
							if ($cnt == 3) {
								return [$i, round($mid)];
							}
						}
						else {
							$cnt = 0;
						}
						$l_r = $r;
					}
				}
				$l = $r;
			}
		}
	}

	return [$sx - round(abs($mid-$sy)/sqrt(3)), round($mid)];;
}

function screencap() {
  ob_start();

	system('adb shell screencap -p /sdcard/screen.png');
	system('adb pull /sdcard/screen.png .');

  ob_end_clean();
}

function press($time) {
    // 随机点按下和稍微挪动抬起，模拟手指
    $px = rand(300,400);
    $py = rand(400,600);
    $ux = $px + rand(-10,10);
    $uy = $py + rand(-10,10);
    $swipe = sprintf("%s %s %s %s", $px, $py, $ux, $uy);
    system('adb shell input swipe ' . $swipe . ' ' . $time);
}

for ($id = 0; ; $id++) {
  echo sprintf("#%05d: ", $id);
    // 截图
	screencap();
    // 获取坐标
	$image = imagecreatefrompng('screen.png');
	list($sx, $sy) = getStart();
	list($tx, $ty) = getEnd();

  if ($sx == 0) break;

	echo sprintf("(%d, %d) -> (%d, %d) ", $sx, $sy, $tx, $ty);
    // 图像描点
	imagefilledellipse($image, $sy, $sx, 10, 10, 0xFF0000);
	imagefilledellipse($image, $ty, $tx, 10, 10, 0xFF0000);

	//imagepng($image, 'screen.scan.png');
	 //引用绝对路径，防止 报错 / screen目录 下无截图
	imagepng($image, sprintf(dirname(__FILE__)."/screen/%05d.png", $id));

    // 计算按压时间
	$dist = sqrt(pow($tx - $sx, 2) + pow($ty - $sy, 2));

  // 2.5D距离修正
  $trdeg = rad2deg(asin(abs($tx - $sx) / $dist));

  $dist_fix = $dist * sin(deg2rad(150 - $trdeg));
  $time = pow($dist_fix, PRESS_EXP) * PRESS_TIME;
  $time = round($time);
  echo sprintf("dist: %f, dist_fix: %f, trdeg: %f, time: %f", $dist, $dist_fix, $trdeg, $time);
  press($time);

  // 等待下一次截图，随机延迟
  $sleep = SLEEP_TIME_MIN+((SLEEP_TIME_MAX-SLEEP_TIME_MIN)*rand(0,10)*0.1);

  echo sprintf(", sleep: %f\n",$sleep);
  sleep($sleep);
}
