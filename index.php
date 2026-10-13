<?php
/* AI: ChatGPT / 프롬프트: 기존 FRAME 달력을 서버 파일 기반 LA-6으로 바꿔줘.
 * [수정] index.html을 PHP 템플릿으로 변경하고 매번 파일을 읽는다. */
declare(strict_types=1);
require_once __DIR__.'/storage.php';
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
if (!@session_start()) { http_response_code(500); exit('세션 저장 경로를 확인해 주세요.'); }
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']); session_write_close();
[$list,$notice,$readable] = readScreenings();
$today = date('Y-m-d');
$values = ($flash && !$flash['success']) ? $flash['values'] : ['date'=>$today,'title'=>'','time'=>'19:00','duration'=>'120','genre'=>'SF','rating'=>'전체','price'=>'15000','language'=>'자막','note'=>''];
$errors = $flash['errors'] ?? [];
$target = $_GET['date'] ?? $today;
if (!is_string($target) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$target,$p) !== 1
    || (int)$p[1] < 1900 || (int)$p[1] > 2100 || !checkdate((int)$p[2],(int)$p[3],(int)$p[1])) $target = $today;
$boot = ['screenings'=>$list,'today'=>$today,'date'=>$target,'retry'=>(bool)$errors];
function renderField(string $field, array $values, array $errors, string $today): void {
    $value = $values[$field];
    echo '<label for="'.h($field).'">'.h(LABELS[$field]).($field==='note'?' (선택)':'');
    $common = ' id="'.$field.'" name="'.$field.'" aria-describedby="'.$field.'Error"'.(isset($errors[$field])?' aria-invalid="true"':'');
    if (isset(CHOICES[$field])) {
        echo '<select'.$common.' required>';
        if (!in_array($value,CHOICES[$field],true)) echo '<option value="'.h($value).'" selected>'.h($value===''?'선택해 주세요':$value.' (허용되지 않은 값)').'</option>';
        foreach (CHOICES[$field] as $option) echo '<option'.($option===$value?' selected':'').'>'.h($option).'</option>';
        echo '</select>';
    } elseif ($field === 'note') echo '<textarea'.$common.' maxlength="300" rows="2">'.h($value).'</textarea>';
    else {
        $types = ['date'=>'date','title'=>'text','time'=>'time','duration'=>'number','price'=>'number'];
        $attrs = ['date'=>' min="'.$today.'" max="2100-12-31"','title'=>' maxlength="60"','time'=>' step="60"','duration'=>' min="1" max="600" step="1"','price'=>' min="0" max="100000" step="500"'];
        // [수정] 잘못된 날짜/숫자도 사라지지 않게 오류가 있을 때 text로 복원한다.
        echo '<input type="'.(isset($errors[$field])?'text':$types[$field]).'"'.$common.' value="'.h($value).'" required'.$attrs[$field].'>';
    }
    echo '<span class="field-error" id="'.$field.'Error">'.h(implode("\n",$errors[$field]??[])).'</span></label>';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<!-- AI: ChatGPT / 프롬프트: LA-6의 서버 파일 저장, 검사, 포스터, 안전한 달력 표시를 구현해줘. [수정] -->
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>FRAME — 영화 상영 편성</title>
<link rel="icon" href="data:,">
<link rel="stylesheet" href="style.css">
<script src="js/validation.js" defer></script>
<script src="js/app.js" defer></script>
</head>
<body>
<a href="#main" class="skip">본문 바로가기</a>
<aside class="sidebar">
  <a class="brand" href="index.php"><span class="brand-icon">F</span>FRAME<span class="brand-dot">®</span></a>
  <div class="workspace"><span class="workspace-icon">01</span><div>우리의 상영관<small>SCREENING ROOM</small></div></div>
  <p class="nav-label">WORKSPACE</p>
  <nav><button id="navCalendar" class="nav-item active"><span>▦</span>상영 캘린더</button><button id="navList" class="nav-item"><span>☷</span>전체 상영 목록</button><button id="help" class="nav-item"><span>?</span>이용 안내</button></nav>
  <div class="sidebar-bottom"><div class="local-note"><span class="signal"></span>함께 보는 상영표<small>등록한 상영은 다시 열어도 남아 있습니다.</small></div><div class="sidebar-foot"><span>FRAME</span><span>LA-6</span></div></div>
</aside>
<div class="app">
<header class="topbar"><div><span class="muted">워크스페이스</span><span class="crumb">/</span>상영 관리</div><div class="top-actions"><span id="saveStatus">함께 보는 상영표</span><button id="theme" class="icon-button" aria-label="밝은 테마로 변경">◐</button></div></header>
<main id="main">
<section class="page-heading"><div><div class="eyebrow">THE NEXT SCENE STARTS HERE</div><h1>상영 캘린더<span class="accent">.</span></h1><p>상영을 등록하고 날짜별 편성을 확인하세요.</p></div><div class="heading-actions"><button id="print" class="button secondary">인쇄</button><button id="add" class="button primary">＋ 상영 등록</button></div></section>
<?php if ($notice !== ''): ?><p class="notice" role="status"><?= h($notice) ?></p><?php endif; ?>
<?php if ($flash && $flash['success']): ?><p class="notice success" role="status">상영을 등록했습니다. 새로고침해도 다시 등록되지 않습니다.</p><?php endif; ?>
<?php if ($errors): ?>
<div class="error" role="alert"><strong>서버 검사 실패 · 등록하지 않았습니다.</strong><ul>
<?php foreach ($errors as $field=>$messages): foreach ($messages as $message): ?><li><?= h(LABELS[$field].': '.$message) ?></li><?php endforeach; endforeach; ?>
</ul></div>
<?php endif; ?>
<noscript><p class="notice">달력과 모달을 사용하려면 JavaScript를 켜 주세요.</p></noscript>
<section class="stats" aria-label="이번 달 편성 요약"><div><span>이번 달 상영</span><strong id="statCount"></strong><em>MONTHLY SCREENINGS</em></div><div><span>상영 작품</span><strong id="statFilms"></strong><em>UNIQUE TITLES</em></div><div><span>편성한 날</span><strong id="statDays"></strong><em>PROGRAMMED DAYS</em></div><div><span>총 상영 시간</span><strong id="statHours"></strong><em>SCREEN TIME</em></div></section>
<section class="schedule" aria-labelledby="monthTitle">
<div class="calendar-toolbar"><div class="month-controls"><h2 id="monthTitle">상영표</h2><button id="prev" class="icon-button" aria-label="이전 달">‹</button><button id="next" class="icon-button" aria-label="다음 달">›</button><button id="today" class="small-button">오늘</button></div><div class="month-pickers"><label class="sr-only" for="year">연도</label><select id="year"></select><label class="sr-only" for="month">월</label><select id="month"></select><div class="view-switch"><button id="calendarView" class="selected" aria-pressed="true">▦ 달력</button><button id="listView" aria-pressed="false">☷ 목록</button></div></div></div>
<div class="filterbar"><label class="search-box"><span>⌕</span><input id="search" type="search" placeholder="영화 제목 검색" aria-label="영화 제목 검색"></label><select id="genreFilter" aria-label="장르 필터"><option value="">모든 장르</option><?php foreach (CHOICES['genre'] as $o): ?><option><?= h($o) ?></option><?php endforeach; ?></select><select id="ratingFilter" aria-label="등급 필터"><option value="">모든 등급</option><?php foreach (CHOICES['rating'] as $o): ?><option><?= h($o) ?></option><?php endforeach; ?></select><button id="resetFilter" class="text-button" hidden>필터 초기화</button><span id="resultCount" class="filter-count"></span></div>
<div class="calendar-scroll" id="calendarPanel"><table class="calendar"><caption class="sr-only">월별 영화 상영표. 날짜를 눌러 상영을 등록하세요.</caption><thead><tr><th scope="col">일</th><th scope="col">월</th><th scope="col">화</th><th scope="col">수</th><th scope="col">목</th><th scope="col">금</th><th scope="col">토</th></tr></thead><tbody id="calendarBody"></tbody></table></div>
<div id="listPanel" class="list-panel" hidden></div>
<div class="calendar-footer"><span>상영 사이 10분 정리 시간 · 청불은 18시 이후</span><span>날짜를 눌러 새 상영을 등록하세요</span></div>
</section>
<section id="emptyBanner" class="empty-banner"><div><strong>첫 번째 상영을 기다리고 있어요.</strong><p>상영 등록 버튼이나 달력의 날짜를 눌러 시작하세요.</p></div></section>
<footer class="main-footer">FRAME / EVERY FRAME MATTERS.</footer>
</main></div>
<dialog id="editor" aria-labelledby="editorTitle">
<!-- [수정] 포스터 전송에 필요한 multipart. 등록 기능만 제공한다. -->
<form id="screeningForm" action="add.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_POSTER_BYTES ?>">
<div class="dialog-heading"><div><span class="eyebrow">SCREENING</span><h2 id="editorTitle">새 상영 등록</h2></div><button type="button" class="icon-button" data-close aria-label="닫기">×</button></div>
<p class="dialog-intro">입력한 내용을 확인하고 상영을 등록합니다.</p>
<?php renderField('date',$values,$errors,$today); renderField('title',$values,$errors,$today); ?>
<div class="form-grid"><?php foreach (['time','duration','genre','rating','price','language'] as $f) renderField($f,$values,$errors,$today); ?></div>
<?php renderField('note',$values,$errors,$today); ?>
<label for="poster">포스터 (선택)<input id="poster" name="poster" type="file" accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="posterError"><span class="field-error" id="posterError"><?= h(implode("\n",$errors['poster']??[])) ?></span></label>
<p class="form-hint">JPEG · PNG · GIF · WebP, 최대 2MiB. 실패 후에는 파일을 다시 선택하세요.</p>
<div id="formError" class="error" role="alert" tabindex="-1" hidden></div>
<div class="dialog-actions"><button type="button" class="button secondary" data-close>닫기</button><button type="submit" class="button primary">등록</button></div>
</form></dialog>
<dialog id="detail" aria-labelledby="detailTitle"><div class="dialog-heading"><span class="eyebrow">SCREENING DETAILS</span><button class="icon-button" data-close aria-label="닫기">×</button></div><div id="detailContent"></div><div class="dialog-actions"><button class="button secondary" data-close>닫기</button></div></dialog>
<dialog id="helpDialog" aria-labelledby="helpTitle"><div class="dialog-heading"><h2 id="helpTitle">FRAME 이용 안내</h2><button class="icon-button" data-close aria-label="닫기">×</button></div><ol class="help-list"><li>날짜를 누르면 등록 폼이 열립니다. 지난 날짜는 등록할 수 없습니다.</li><li>상영을 누르면 전체 정보와 선택한 포스터가 보입니다.</li><li>같은 날짜·시간 중복 및 상영 시간 겹침은 등록할 수 없습니다.</li><li><kbd>N</kbd> 새 상영 · <kbd>/</kbd> 검색 · <kbd>Esc</kbd> 모달 닫기.</li></ol></dialog>
<!-- [수정] script 종료 문자열도 안전하게 JSON으로 전달한다. -->
<script id="calendarData" type="application/json"><?= json_encode($boot,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
</body>
</html>
