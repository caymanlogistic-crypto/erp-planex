<?php
$activeCount=count(array_filter($logists??[],static fn(array $l):bool=>($l['status']??'')==='active'));
$blockedCount=count(array_filter($logists??[],static fn(array $l):bool=>($l['status']??'')==='blocked'));
$seniorCount=count(array_filter($logists??[],static fn(array $l):bool=>($l['role_code']??'')==='senior_logist'));
$initials=static function(string $name):string{$parts=preg_split('/\s+/u',trim($name))?:[];$out='';foreach(array_slice($parts,0,2) as $p){if($p!=='')$out.=mb_strtoupper(mb_substr($p,0,1));}return $out?:'Л';};
?>
<?php if($company===null):?><div class="notice warn">Компания не найдена.</div>
<?php elseif(($company['status']??'')!=='active'):?><div class="page-head"><div class="page-head-left"><h1 class="page-title">Логисты</h1></div></div><div class="notice warn">Создание пользователей недоступно.</div>
<?php elseif(isset($dbError)):?><div class="page-head"><div class="page-head-left"><h1 class="page-title">Логисты</h1></div></div><div class="notice warn"><?=e($dbError)?></div>
<?php else:?>
<div class="ux-shell" data-ux-page="logists">
 <div class="page-head"><div class="page-head-left"><h1 class="page-title">Логисты</h1><div class="page-summary"><span>Команда, роли и доступ сотрудников к рабочему контуру ПЛАНЭКС.</span></div></div><div class="page-head-right"><button type="button" class="btn btn-primary btn--toolbar" data-company-user-create-modal>+ Пользователь</button></div></div>
 <div class="ux-summary-strip"><div><strong>Управление командой:</strong> роль определяет набор возможностей, статус — может ли сотрудник войти и работать сейчас.</div><div><?=count($logists??[])?> пользователей</div></div>
 <div class="ux-kpi-grid cols-3">
  <div class="ux-kpi is-good"><div class="ux-kpi-label">Активные сотрудники</div><div class="ux-kpi-value"><?=$activeCount?></div><div class="ux-kpi-note">могут работать в ERP</div></div>
  <div class="ux-kpi is-accent"><div class="ux-kpi-label">Старшие логисты</div><div class="ux-kpi-value"><?=$seniorCount?></div><div class="ux-kpi-note">расширенная роль «Логист+»</div></div>
  <div class="ux-kpi<?=$blockedCount?' is-bad':' is-muted'?>"><div class="ux-kpi-label">Заблокированы</div><div class="ux-kpi-value"><?=$blockedCount?></div><div class="ux-kpi-note">доступ временно закрыт</div></div>
 </div>
 <div class="ux-section">
  <div class="ux-section-head"><div><div class="ux-section-title">Команда</div><div class="ux-section-sub">Двойной клик по сотруднику также открывает карточку.</div></div><div class="ux-section-actions"><input type="search" class="field-input ux-inline-search" id="logists-search" placeholder="Найти по имени или логину"></div></div>
  <?php if(empty($logists)):?><div class="ux-empty"><strong>Логисты ещё не созданы</strong>Создайте первого пользователя и назначьте ему рабочую роль.</div>
  <?php else:?><div class="ux-team-list" id="logists-list">
   <?php foreach($logists as $l):$name=(string)($l['full_name']??'');$role=$l['role_code']??'logist';$status=$l['status']??'';$hay=mb_strtolower($name.' '.($l['login']??'').' '.$role.' '.$status);?>
   <div class="ux-team-row" data-company-user-id="<?=(int)$l['id']?>" data-logist-row data-search="<?=e($hay)?>">
    <div class="ux-avatar"><?=e($initials($name))?></div>
    <div class="ux-primary-cell"><strong><?=e($name?:'Без имени')?></strong><span>@<?=e($l['login']??'—')?></span></div>
    <div><?php if($role==='senior_logist'):?><span class="badge badge-warning"><span class="dot"></span>Логист+</span><?php else:?><span class="badge badge-neutral"><span class="dot"></span>Логист</span><?php endif;?></div>
    <div><?php if($status==='active'):?><span class="badge badge-ok"><span class="dot"></span>Активен</span><?php elseif($status==='blocked'):?><span class="badge badge-danger"><span class="dot"></span>Заблокирован</span><?php elseif($status==='archived'):?><span class="badge badge-neutral"><span class="dot"></span>В архиве</span><?php else:?><span class="badge badge-neutral"><?=e($status?:'—')?></span><?php endif;?></div>
    <div class="ux-actions"><a href="<?=app_url('/company/logists/'.$l['id'])?>" class="btn btn-secondary btn-sm">Карточка</a><a href="<?=app_url('/company/logists/'.$l['id'].'/edit')?>" class="btn btn-ghost btn-sm">Настройки</a></div>
   </div><?php endforeach;?>
  </div><div class="ux-empty ux-hidden" id="logists-empty"><strong>Ничего не найдено</strong>Измените поисковый запрос.</div><?php endif;?>
 </div>
</div>
<script>document.addEventListener('DOMContentLoaded',function(){var q=document.getElementById('logists-search'),rows=[].slice.call(document.querySelectorAll('[data-logist-row]')),empty=document.getElementById('logists-empty');if(!q)return;q.addEventListener('input',function(){var v=q.value.trim().toLowerCase(),n=0;rows.forEach(function(r){var ok=!v||(r.dataset.search||'').indexOf(v)!==-1;r.classList.toggle('ux-hidden',!ok);if(ok)n++});if(empty)empty.classList.toggle('ux-hidden',n!==0)})});</script>

<div class="modal-overlay driver-view-overlay" id="company-user-view-modal" data-close-on-overlay="0" data-close-on-escape="0" data-reset-on-close="1"><div class="modal modal-lg user-view-modal-inner"><div class="modal-head"><span class="modal-title">Пользователь</span><button type="button" class="modal-close" data-company-user-view-close>✕</button></div><div class="modal-body"><div class="driver-modal-loading">...</div></div></div></div>
<div class="modal-overlay" id="company-user-create-modal" data-close-on-overlay="0" data-close-on-escape="0" data-reset-on-close="1"><div class="modal modal-lg"><div class="modal-head"><span class="modal-title">Создать пользователя</span><button type="button" class="modal-close" data-company-user-create-close>✕</button></div><?php $generatedPassword=generatePassword();$formError=null;$errors=[];$old=[];require base_path('app/View/partials/company_user_create_form.php');?></div></div>
<?php endif;?>