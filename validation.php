<?php
/* AI: ChatGPT / 프롬프트: LA-5 검사에 지난 날짜, 중복, 포스터 검사를 추가해줘.
 * [수정] 오류를 모두 모으고, 제목 원문은 삭제하거나 변경하지 않는다. */
declare(strict_types=1);
date_default_timezone_set('Asia/Seoul');
const LABELS = ['date'=>'상영 날짜','title'=>'영화 제목','time'=>'시작 시간','duration'=>'러닝타임','genre'=>'장르','rating'=>'관람 등급','price'=>'관람료','language'=>'상영 언어','note'=>'편성 메모','poster'=>'포스터','storage'=>'저장'];
const CHOICES = ['genre'=>['SF','드라마','애니메이션','스릴러','로맨스','액션','다큐멘터리'],'rating'=>['전체','12세','15세','청불'],'language'=>['원어','자막','더빙']];
const MAX_POSTER_BYTES = 2 * 1024 * 1024;
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function addError(array &$errors, string $field, string $message): void { $errors[$field][] = $message; }
function utf16Length(string $s): int {
    preg_match_all('/./us', $s, $chars);
    return array_sum(array_map(fn($c) => strlen($c) === 4 ? 2 : 1, $chars[0]));
}
function minute(string $time): int { return (int)substr($time,0,2)*60 + (int)substr($time,3,2); }
function numericValue(string $s): bool {
    return preg_match('/^[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?$/D', $s) === 1 && is_finite((float)$s);
}
function validateInput(array $post, array $records = [], bool $new = true): array {
    $values = []; $errors = [];
    foreach (array_diff(array_keys(LABELS), ['poster','storage']) as $field) {
        $raw = $post[$field] ?? '';
        if (!is_string($raw) || preg_match('//u', $raw) !== 1) {
            $values[$field] = '';
            addError($errors,$field,LABELS[$field].'은 올바른 UTF-8 문자열 하나로 입력해 주세요.');
        } else { $values[$field] = $raw; }
    }
    $v = $values;
    $dateOK = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$v['date'],$p) === 1
        && (int)$p[1] >= 1900 && (int)$p[1] <= 2100 && checkdate((int)$p[2],(int)$p[3],(int)$p[1]);
    if (!$dateOK) addError($errors,'date','1900~2100년 사이의 실제 날짜를 입력해 주세요.');
    elseif ($new && $v['date'] < date('Y-m-d')) addError($errors,'date','지난 날짜에는 상영을 등록할 수 없습니다.');
    if (preg_match('/^[\x{0009}-\x{000D}\x{0020}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]*$/uD',$v['title']) === 1)
        addError($errors,'title','영화 제목은 비워 두거나 공백만 입력할 수 없습니다.');
    if (utf16Length($v['title']) > 60) addError($errors,'title','영화 제목은 60자 이내로 입력해 주세요.');
    $timeOK = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D',$v['time']) === 1;
    if (!$timeOK) addError($errors,'time','시작 시간은 00:00~23:59의 HH:MM 형식이어야 합니다.');
    $duration = (float)$v['duration']; $price = (float)$v['price'];
    $durationOK = numericValue($v['duration']) && floor($duration) === $duration && $duration >= 1 && $duration <= 600;
    if (!$durationOK) addError($errors,'duration','러닝타임은 1~600분의 정수여야 합니다.');
    if (!numericValue($v['price']) || floor($price) !== $price || $price < 0 || $price > 100000 || fmod($price,500) != 0)
        addError($errors,'price','관람료는 0~100,000원 사이의 500원 단위여야 합니다.');
    foreach (CHOICES as $field=>$options) if (!in_array($v[$field],$options,true)) addError($errors,$field,'제공된 선택지 중에서 선택해 주세요.');
    if (utf16Length($v['note']) > 300) addError($errors,'note','편성 메모는 300자 이내로 입력해 주세요.');
    if ($timeOK && $v['rating'] === '청불' && $v['time'] < '18:00') addError($errors,'time','청불 영화는 18:00 이후에 시작해야 합니다.');
    if ($timeOK && $durationOK && minute($v['time']) + $duration > 1440) addError($errors,'duration','상영은 당일 24:00까지 끝나야 합니다.');
    // [수정] 서버 파일과 비교. 기존 10분 정리 시간 조건도 양쪽에서 유지한다.
    if ($dateOK && $timeOK) foreach ($records as $s) {
        if ($s['date'] !== $v['date']) continue;
        if ($s['time'] === $v['time']) { addError($errors,'time','같은 날 같은 시간에 이미 등록된 상영이 있습니다.'); break; }
        if ($durationOK && minute($v['time']) < minute($s['time'])+$s['duration']+10 && minute($s['time']) < minute($v['time'])+$duration+10) {
            addError($errors,'time','기존 상영과 겹칩니다. 상영 사이 10분을 확보해 주세요.'); break;
        }
    }
    return [$values,$errors];
}
function validatePoster(array $files, array &$errors): ?array {
    $f = $files['poster'] ?? null;
    if ($f === null) return null;
    if (!is_array($f) || !isset($f['error']) || !is_int($f['error'])) {
        addError($errors,'poster','포스터는 파일 하나만 선택해 주세요.'); return null;
    }
    if ($f['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) {
        addError($errors,'poster',in_array($f['error'],[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true)?'포스터는 2MiB 이하만 올릴 수 있습니다.':'포스터 업로드에 실패했습니다. 다시 선택해 주세요.'); return null;
    }
    if (!is_string($f['tmp_name'] ?? null) || !is_uploaded_file($f['tmp_name'])) {
        addError($errors,'poster','정상적으로 업로드된 파일이 아닙니다.'); return null;
    }
    $size = @filesize($f['tmp_name']);
    if ($size === false || $size === 0 || $size > MAX_POSTER_BYTES) addError($errors,'poster','포스터는 비어 있지 않은 2MiB 이하 파일이어야 합니다.');
    $info = @getimagesize($f['tmp_name']);
    $mime = @(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $types = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    if ($info === false || !isset($types[$mime]) || ($info['mime'] ?? '') !== $mime) {
        addError($errors,'poster','실제 JPEG, PNG, GIF, WebP 이미지 파일만 올릴 수 있습니다.'); return null;
    }
    return ['tmp'=>$f['tmp_name'],'ext'=>$types[$mime]];
}
