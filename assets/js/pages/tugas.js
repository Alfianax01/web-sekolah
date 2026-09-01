// assets/js/pages/tugas.js - Modal Tugas & Pengumpulan
function openCreateTaskModal() {
    openModal('modal-create-task');
}

function openSubmitTaskModal(preselectedId) {
    var select = document.getElementById('submit-tugas-select');
    if (select && preselectedId) {
        select.value = preselectedId;
    }
    openModal('modal-submit-task');
}

function openDeleteSubmissionModal(id, studentName) {
    document.getElementById('del-sub-id').value = id;
    document.getElementById('del-sub-name').innerText = 'Siswa: ' + studentName;
    openModal('modal-delete-submission');
}

function openPdfModal(filePath, title) {
    document.getElementById('pdf-title').innerHTML = '📄 ' + title;
    document.getElementById('pdf-iframe').src = filePath;
    openModal('modal-pdf');
}

// Reset iframe on close
var origCloseModalTugas = window.closeModal;
window.closeModal = function(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.remove('active');
    if (id === 'modal-pdf') {
        var iframe = document.getElementById('pdf-iframe');
        if (iframe) iframe.src = '';
    }
};
