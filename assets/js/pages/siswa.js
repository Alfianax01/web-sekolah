// assets/js/pages/siswa.js - Modal CRUD Siswa
function openAddModal() {
    document.getElementById('modal-title').innerHTML = '➕ Tambah Nilai Mata Pelajaran';
    document.getElementById('form-mode').value = 'insert';
    document.getElementById('form-nis').value = '';
    document.getElementById('form-nis').readOnly = false;
    document.getElementById('form-nis').style.background = '#ffffff';
    document.getElementById('form-nama').value = '';
    document.getElementById('form-ttl').value = '';
    document.getElementById('form-kelas').value = '';
    document.getElementById('form-mapel').value = '';
    document.getElementById('form-mapel').disabled = false;
    document.getElementById('form-mapel').style.background = '#ffffff';
    document.getElementById('form-jurusan').value = '';
    document.getElementById('form-guru').value = '';
    document.getElementById('form-nilai').value = '';
    openModal('modal-siswa');
}

function openEditSiswaModal(data) {
    document.getElementById('edit-nis').value = data.nis;
    document.getElementById('edit-nama').value = data.nama_siswa;
    document.getElementById('edit-kelas').value = data.kelas || '';
    document.getElementById('edit-jurusan').value = data.id_jurusan || '';
    openModal('modal-edit-siswa');
}

function openDeleteSiswaModal(nis, nama) {
    document.getElementById('del-siswa-nis').value = nis;
    document.getElementById('del-siswa-name').innerText = nama + ' (NIS: ' + nis + ')';
    openModal('modal-delete-siswa');
}
