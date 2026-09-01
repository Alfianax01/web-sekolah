// assets/js/pages/mapel.js - Modal CRUD Mapel
function openAddModal() {
    document.getElementById('modal-title').innerHTML = '➕ Tambah Mata Pelajaran Baru';
    document.getElementById('form-mode').value = 'insert';
    document.getElementById('form-id').value = '';
    document.getElementById('form-id').readOnly = false;
    document.getElementById('form-id').style.background = '#ffffff';
    document.getElementById('form-nama').value = '';
    document.getElementById('form-jurusan').value = '';
    openModal('modal-mapel');
}

function openEditModal(data) {
    document.getElementById('modal-title').innerHTML = '✏️ Edit Mata Pelajaran: ' + (data.nama_mapel || '');
    document.getElementById('form-mode').value = 'update';
    document.getElementById('form-id').value = data.id_mapel || '';
    document.getElementById('form-id').readOnly = true;
    document.getElementById('form-id').style.background = '#f1f5f9';
    document.getElementById('form-nama').value = data.nama_mapel || '';
    document.getElementById('form-jurusan').value = data.id_jurusan || '';
    openModal('modal-mapel');
}

function openDeleteModal(idMapel, mapelName) {
    document.getElementById('del-id').value = idMapel;
    document.getElementById('del-mapel-name').innerText = mapelName + ' (Kode: ' + idMapel + ')';
    openModal('modal-delete');
}
