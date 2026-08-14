<?php if($company===null):?><div class="notice warn">Компания не найдена.</div>
<?php elseif(($company['status']??'')!=='active'):?><div class="page-head"><div class="page-head-left"><h1 class="page-title">Настройки выписок</h1></div></div><div class="notice warn">Работа с настройками выписок недоступна.</div>
<?php elseif(isset($dbError)):?><div class="page-head"><div class="page-head-left"><h1 class="page-title">Настройки выписок</h1></div></div><div class="notice warn"><?=e($dbError)?></div>
<?php else:$currSetting=$bankSettings[0]??null;$isActive=!$currSetting||!empty($currSetting['is_active']);?>
<div class="ux-shell" data-ux-page="bank-statement-settings">
 <div class="page-head"><div class="page-head-left"><h1 class="page-title">Настройки выписок</h1><div class="page-summary"><span>Автоматическое получение банковских выписок из защищённого почтового ящика.</span></div></div></div>
 <div class="ux-summary-strip"><div><strong>Схема:</strong> ERP подключается к почте → находит письма банка по отправителю и теме → импортирует вложенную выписку в «Банк».</div><div><span class="<?=$isActive?'badge badge-ok':'badge badge-neutral'?>"><span class="dot"></span><?=$isActive?'Импорт включён':'Импорт выключен'?></span></div></div>
 <div class="ux-settings-grid">
  <section class="ux-settings-card">
   <div class="ux-settings-card-head"><h2>Подключение к выпискам</h2><p>Изменяйте параметры только при смене почтового ящика или формата писем банка.</p></div>
   <div class="ux-settings-card-body">
    <form action="<?=app_url('/company/finance/bank-accounts/settings')?>" method="post" data-bank-settings-form>
     <?=csrfField()?><input type="hidden" name="imap_ssl" value="1"><?php if($currSetting):?><input type="hidden" name="setting_id" value="<?=(int)$currSetting['id']?>"><?php endif;?>
     <div class="ux-form-section"><div class="ux-form-section-title">Источник</div><div class="ux-form-grid cols-3">
      <div class="field"><label class="field-label">Банк</label><select name="bank_code" class="field-select"><option value="vtb" <?=$currSetting&&$currSetting['bank_code']==='vtb'?'selected':''?>>ВТБ</option></select></div>
      <div class="field"><label class="field-label">IMAP-хост</label><input type="text" name="imap_host" class="field-input" value="<?=e($currSetting['imap_host']??'')?>" placeholder="imap.example.ru"></div>
      <div class="field"><label class="field-label">Порт</label><input type="number" name="imap_port" class="field-input" value="<?=(int)($currSetting['imap_port']??993)?>" min="1" max="65535"><div class="field-hint">Обычно 993 для IMAPS.</div></div>
     </div></div>
     <div class="ux-form-section"><div class="ux-form-section-title">Доступ к почтовому ящику</div><div class="ux-form-grid">
      <div class="field"><label class="field-label">Имя пользователя</label><input type="text" name="username" class="field-input" value="<?=e($currSetting['username']??'')?>" placeholder="mailbox@example.ru" autocomplete="off"></div>
      <div class="field"><label class="field-label">Пароль</label><input type="password" name="password" class="field-input" placeholder="<?=$currSetting&&!empty($currSetting['has_password'])?'•••••• · оставьте пустым, чтобы не менять':''?>" autocomplete="new-password"><div class="field-hint"><?=$currSetting&&!empty($currSetting['has_password'])?'Пароль уже сохранён.':'Укажите пароль почтового ящика.'?></div></div>
     </div></div>
     <div class="ux-form-section"><div class="ux-form-section-title">Какие письма считать выписками</div><div class="ux-form-grid">
      <div class="field"><label class="field-label">Отправитель</label><input type="text" name="sender_filter" class="field-input" value="<?=e($currSetting['sender_filter']??'vtb-inform@vtb.ru')?>"></div>
      <div class="field"><label class="field-label">Тема письма содержит</label><input type="text" name="subject_filter" class="field-input" value="<?=e($currSetting['subject_filter']??'Регулярная выписка')?>"></div>
     </div></div>
     <div class="ux-form-section" style="display:flex;align-items:center;justify-content:space-between;gap:12px"><div class="form-toggle"><input type="hidden" name="is_active" value="0"><label class="checkbox-item"><input type="checkbox" name="is_active" value="1" <?=$isActive?'checked':''?>><span class="checkbox-mark"></span><span class="checkbox-label">Автоматический импорт активен</span></label></div><button type="submit" class="btn btn-primary">Сохранить настройки</button></div>
    </form>
   </div>
  </section>
  <aside class="ux-settings-card">
   <div class="ux-settings-card-head"><h2>Как работает импорт</h2><p>Короткая проверка перед изменением настроек.</p></div>
   <div class="ux-settings-card-body"><div class="ux-status-list">
    <div class="ux-status-item"><span class="ux-status-dot ok"></span><div><strong>Защищённое соединение</strong><span>TLS включён принудительно, сертификат почтового сервера проверяется.</span></div></div>
    <div class="ux-status-item"><span class="ux-status-dot"></span><div><strong>Отбор писем</strong><span>ERP берёт только письма, которые подходят одновременно по отправителю и теме.</span></div></div>
    <div class="ux-status-item"><span class="ux-status-dot"></span><div><strong>Импорт в «Банк»</strong><span>Операции из выписки попадают в банковский журнал, после чего применяются правила разнесения.</span></div></div>
    <div class="ux-status-item"><span class="ux-status-dot"></span><div><strong>Защита от повторов</strong><span>Уже импортированные банковские операции не должны создаваться повторно.</span></div></div>
   </div><div class="ux-tech-note" style="margin-top:14px">Если банк продолжает присылать письма как раньше, эти настройки лучше не менять. Для проверки результата используйте раздел «Банк».</div></div>
  </aside>
 </div>
</div>
<?php endif;?>