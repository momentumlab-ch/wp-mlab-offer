/* Termine im Erfassungsformular hinzufügen und entfernen. */
document.querySelectorAll('.mlab-dates').forEach(function (box) {
	var template = box.querySelector('.mlab-date');
	box.parentNode.querySelector('.mlab-add').addEventListener('click', function () {
		var row = template.cloneNode(true);
		row.querySelector('input').value = '';
		box.appendChild(row);
		row.querySelector('input').focus();
	});
	box.addEventListener('click', function (e) {
		if (!e.target.classList.contains('mlab-del')) return;
		var rows = box.querySelectorAll('.mlab-date');
		if (rows.length > 1) e.target.parentNode.remove();
		else rows[0].querySelector('input').value = '';
	});
});
