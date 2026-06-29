<div class="modal-overlay" id="delete-confirm-modal" data-close-on-overlay="1" data-close-on-escape="1" style="display:none;">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">Удалить запись?</span>
      <button type="button" class="modal-close" onclick="closeModal('delete-confirm-modal')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="driver-delete-confirm-title">Запись будет удалена из списка.</div>
      <div class="driver-delete-confirm-text">Для подтверждения введите <b>УДАЛИТЬ</b>.</div>
      <div class="field" style="margin-top:1rem;">
        <input type="text" class="field-input" id="delete-confirm-input" autocomplete="off" placeholder="Введите УДАЛИТЬ" oninput="document.getElementById('delete-confirm-btn').disabled = this.value.trim() !== 'УДАЛИТЬ'">
      </div>
      <div class="is-hidden" id="delete-confirm-error" style="color:var(--red);margin-top:8px;"></div>
    </div>
    <div class="modal-foot is-spaced">
      <div class="modal-foot-actions">
        <button type="button" class="btn btn-ghost" onclick="closeModal('delete-confirm-modal')">Отмена</button>
        <button type="button" class="btn btn-danger" id="delete-confirm-btn" disabled onclick="submitDeleteForm()">Удалить</button>
      </div>
    </div>
  </div>
</div>
