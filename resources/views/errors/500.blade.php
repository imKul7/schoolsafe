@extends('errors.layout')

@section('code', '500')

@section('title', 'Terjadi gangguan pada sistem')

@section('status', 'Kesalahan internal')

@section('message')
    SchoolSafe belum dapat memproses permintaan Anda karena terjadi
    gangguan internal.
@endsection

@section('guidance')
    Coba muat ulang halaman beberapa saat lagi. Apabila gangguan terus
    terjadi, sampaikan waktu kejadian dan aktivitas terakhir kepada
    administrator tanpa mengirimkan password atau informasi rahasia.
@endsection