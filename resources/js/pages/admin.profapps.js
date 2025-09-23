// Admin Professional Applications page: rejection with modalConfirm
(function(){
	if ((document.body.dataset.page || '') !== 'admin-professional-apps') return;
	const onRejectClick = function(){
		const $form = $(this).closest('form.js-reject-form');
		if (!$form.length || !window.modalConfirm) return;
		const body = document.createElement('div');
		body.innerHTML = `
			<div>
				<label class="form-label">Motivo de rechazo (opcional)</label>
				<textarea class="form-control" id="rejectNotes" rows="4" placeholder="Escribe una breve justificación..."></textarea>
			</div>
		`;
		modalConfirm({
			modalId: 'rejectReasonModal',
			title: 'Rechazar solicitud',
			icon: 'fa-regular fa-circle-xmark',
			iconColor: '#dc3545',
			btnsType: 'ac',
			body: body.outerHTML,
			onClickYes: () => {
				const notes = document.getElementById('rejectNotes')?.value || '';
				$form.find('input[name="notes"]').val(notes);
				$form.trigger('submit');
			},
		}, 'normal', { centered: true, draggable: true });
	};
	$(document).on('click', '.js-open-reject', onRejectClick);
})();
