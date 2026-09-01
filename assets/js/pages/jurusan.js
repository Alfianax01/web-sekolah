// assets/js/pages/jurusan.js - Modal CRUD Jurusan
function openAddModal() {
    document.getElementById('modal-title').innerHTML = '➕ Tambah Jurusan Baru';
    document.getElementById('form-mode').value = 'insert';
    document.getElementById('form-id').value = '';
    document.getElementById('form-id').readOnly = false;
    document.getElementById('form-id').style.background = '#ffffff';
    document.getElementById('form-nama').value = '';
    openModal('modal-jurusan');
}

function openEditModal(id, nama) {
    document.getElementById('modal-title').innerHTML = '✏️ Edit Jurusan: ' + nama;
    document.getElementById('form-mode').value = 'update';
    document.getElementById('form-id').value = id;
    document.getElementById('form-id').readOnly = true;
    document.getElementById('form-id').style.background = '#f1f5f9';
    document.getElementById('form-nama').value = nama;
    openModal('modal-jurusan');
}

function openDeleteModal(id, nama) {
    document.getElementById('del-id').value = id;
    document.getElementById('del-name').innerText = nama + ' (Kode: ' + id + ')';
    openModal('modal-delete');
}
