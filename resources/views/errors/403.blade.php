@extends('errors.layout')

@section('code', '403')

@section('title', 'Akses ditolak')

@section('status', 'Permintaan tidak diizinkan')

@section('message')
    Anda tidak memiliki izin untuk membuka halaman atau menjalankan
    tindakan tersebut.
@endsection

@section('guidance')
    Pastikan Anda masuk menggunakan akun yang memiliki hak akses sesuai.
    Hubungi administrator sekolah apabila Anda merasa seharusnya dapat
    mengakses halaman ini.
@endsection