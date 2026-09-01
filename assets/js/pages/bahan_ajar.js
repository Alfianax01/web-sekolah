// assets/js/pages/bahan_ajar.js - Modal CRUD Bahan Ajar & PDF
function openUploadModal() {
    var form = document.getElementById('modal-upload').querySelector('form');
    if (form) form.reset();
    openModal('modal-upload');
}

function openDeleteModal(id, title) {
    document.getElementById('del-id').value = id;
    document.getElementById('del-title').innerText = title;
    openModal('modal-delete');
}

function openPdfModal(filePath, title) {
    document.getElementById('pdf-title').innerHTML = '📄 ' + title;
    document.getElementById('pdf-iframe').src = filePath;
    openModal('modal-pdf');
}

// Reset iframe on close
var origCloseModal = window.closeModal;
window.closeModal = function(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.remove('active');
    if (id === 'modal-pdf') {
        var iframe = document.getElementById('pdf-iframe');
        if (iframe) iframe.src = '';
    }
};
