@extends('errors.layout')

@section('code', '429')

@section('title', 'Terlalu banyak permintaan')

@section('status', 'Batas permintaan tercapai')

@section('message')
    Sistem menerima terlalu banyak permintaan dalam waktu singkat
    dari perangkat atau akun Anda.
@endsection

@section('guidance')
    Tunggu beberapa saat sebelum mencoba kembali. Hindari menekan tombol
    berulang kali selama permintaan sedang diproses.
@endsection