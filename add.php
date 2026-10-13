<?php
/* AI: ChatGPT / 프롬프트: 검사를 통과한 상영만 파일에 저장한 뒤 달력으로 돌려보내줘.
 * [수정] 일반 POST + Session + 303 이동(PRG). 출력 전에 header를 보낸다. */
declare(strict_types=1);
require_once __DIR__.'/storage.php';
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: index.php',true,303); exit;
}
if (!@session_start()) { http_response_code(500); exit('세션을 시작하지 못했습니다. PHP 세션 저장 경로를 확인해 주세요.'); }
[$list,$notice,$readable] = readScreenings();
[$values,$errors] = validateInput($_POST,$list);
$poster = validatePoster($_FILES,$errors);
if (!$readable) addError($errors,'storage',$notice);
if ($_POST === [] && $_FILES === [] && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0)
    addError($errors,'poster','요청 용량이 서버 제한을 넘었거나 올바른 폼 요청이 아닙니다. 포스터를 줄여 다시 보내 주세요.');
$success = !$errors && saveScreening($values,$poster,$errors);
$_SESSION['flash'] = ['success'=>$success,'values'=>$values,'errors'=>$errors];
session_write_close();
$date = $values['date'];
$query = preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date) === 1 ? '?date='.rawurlencode($date) : '';
header('Location: index.php'.$query,true,303);
exit;
