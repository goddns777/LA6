<?php
/* AI: ChatGPT / 프롬프트: JSON 파일을 안전하게 읽고 전체 배열을 저장해줘.
 * [수정] 손상 파일을 보존하고, 잠금과 임시 파일 교체로 동시 등록을 보호한다. */
declare(strict_types=1);
require_once __DIR__.'/validation.php';
const DATA_FILE = __DIR__.'/screenings.json';
function readScreenings(): array {
    if (!file_exists(DATA_FILE)) return [[], '저장 파일이 없어 빈 달력을 표시합니다. 첫 등록 때 파일을 만듭니다.', true];
    $text = @file_get_contents(DATA_FILE);
    if ($text === false) return [[], '저장 파일을 읽지 못했습니다. 파일 권한을 확인해 주세요.', false];
    try { $list = json_decode($text,true,512,JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { return [[], '저장 파일이 손상되었습니다. 원본을 복구한 후 등록해 주세요.', false]; }
    // '{}'는 빈 배열과 다르다. 목록/필드/ID/포스터 경로를 확인한다.
    $valid = is_array($list) && array_is_list($list) && str_starts_with(ltrim($text),'[');
    $ids = [];
    if ($valid) foreach ($list as $s) {
        if (!is_array($s) || !is_string($s['id'] ?? null) || preg_match('/^[a-f0-9]{32}$/D',$s['id']) !== 1 || isset($ids[$s['id']])) { $valid=false; break; }
        $ids[$s['id']] = true;
        $raw = $s;
        if (!is_int($s['duration'] ?? null) || !is_int($s['price'] ?? null)) { $valid=false; break; }
        $raw['duration'] = (string)$s['duration']; $raw['price'] = (string)$s['price'];
        [, $errors] = validateInput($raw,[],false); // 과거 상영도 표시할 수 있다.
        $poster = $s['poster'] ?? null;
        if ($errors || ($poster !== null && (!is_string($poster) || preg_match('/^uploads\/[a-f0-9]{32}\.(jpg|png|gif|webp)$/D',$poster) !== 1))) { $valid=false; break; }
    }
    return $valid ? [$list,'',true] : [[], '저장 파일의 자료 형식이 올바르지 않습니다. 원본을 복구해 주세요.', false];
}
function saveScreening(array $values, ?array $poster, array &$errors): bool {
    $lock = @fopen(__DIR__.'/screenings.lock','c');
    if ($lock === false) { addError($errors,'storage','저장 폴더에 쓰기 권한이 없습니다.'); return false; }
    $temp = null; $savedPoster = null; $committed = false;
    try {
        if (!flock($lock,LOCK_EX)) throw new RuntimeException('저장 잠금을 얻지 못했습니다.');
        [$list,$notice,$readable] = readScreenings();
        // [수정] 잠금 안에서 최신 파일을 다시 읽고 검사하여 동시 중복도 막는다.
        [, $latestErrors] = validateInput($values,$list);
        foreach ($latestErrors as $field=>$messages) foreach ($messages as $message) addError($errors,$field,$message);
        if (!$readable) addError($errors,'storage',$notice);
        if ($errors) return false;
        do { $id = bin2hex(random_bytes(16)); } while (in_array($id,array_column($list,'id'),true));
        $relativePoster = null;
        if ($poster !== null) {
            $dir = __DIR__.'/uploads';
            if (!is_dir($dir) && !@mkdir($dir,0755)) throw new RuntimeException('포스터 폴더를 만들 수 없습니다.');
            $relativePoster = 'uploads/'.$id.'.'.$poster['ext'];
            $savedPoster = __DIR__.'/'.$relativePoster;
            if (!@move_uploaded_file($poster['tmp'],$savedPoster)) throw new RuntimeException('포스터를 저장하지 못했습니다.');
        }
        $record = $values;
        $record['duration'] = (int)(float)$values['duration']; $record['price'] = (int)(float)$values['price'];
        $record['id'] = $id; $record['poster'] = $relativePoster;
        $list[] = $record;
        $json = json_encode($list,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
        $temp = @tempnam(__DIR__,'.screenings-');
        if ($temp === false || @file_put_contents($temp,$json) !== strlen($json) || !@rename($temp,DATA_FILE))
            throw new RuntimeException('상영 파일을 저장하지 못했습니다. 폴더 권한과 저장 공간을 확인해 주세요.');
        $committed = true;
        return true;
    } catch (Throwable $e) {
        addError($errors,'storage','저장에 실패했습니다. 폴더 권한과 저장 공간을 확인해 주세요.');
        return false;
    } finally {
        if (is_string($temp) && file_exists($temp)) @unlink($temp);
        if (!$committed && $savedPoster !== null && file_exists($savedPoster)) @unlink($savedPoster);
        flock($lock,LOCK_UN); fclose($lock);
    }
}
