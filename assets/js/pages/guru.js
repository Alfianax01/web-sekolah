// assets/js/pages/guru.js - Modal CRUD Guru
function openAddModal() {
    document.getElementById('modal-title').innerHTML = '➕ Tambah Data Guru Baru';
    document.getElementById('form-mode').value = 'insert';
    document.getElementById('form-nip').value = '';
    document.getElementById('form-nip').readOnly = false;
    document.getElementById('form-nip').style.background = '#ffffff';
    document.getElementById('form-nama').value = '';
    document.getElementById('form-mapel').value = '';
    document.getElementById('form-jk').value = '';
    document.getElementById('form-umur').value = '';
    document.getElementById('form-alamat').value = '';
    openModal('modal-guru');
}

function openEditModal(data) {
    document.getElementById('modal-title').innerHTML = '✏️ Edit Data Guru: ' + (data.nama_guru || '');
    document.getElementById('form-mode').value = 'update';
    document.getElementById('form-nip').value = data.nip || '';
    document.getElementById('form-nip').readOnly = true;
    document.getElementById('form-nip').style.background = '#f1f5f9';
    document.getElementById('form-nama').value = data.nama_guru || '';
    document.getElementById('form-mapel').value = data.id_mapel || '';
    document.getElementById('form-jk').value = data.Jenis_kelamin || '';
    document.getElementById('form-umur').value = data.umur || '';
    document.getElementById('form-alamat').value = data.Alamat || '';
    openModal('modal-guru');
}

function openDeleteModal(nip, guruName) {
    document.getElementById('del-nip').value = nip;
    document.getElementById('del-guru-name').innerText = guruName + ' (NIP: ' + nip + ')';
    openModal('modal-delete');
}
