@extends('errors.layout')

@section('code', '419')

@section('title', 'Sesi telah berakhir')

@section('status', 'Sesi keamanan kedaluwarsa')

@section('message')
    Sesi Anda telah berakhir karena halaman terlalu lama tidak digunakan
    atau token keamanan sudah tidak berlaku.
@endsection

@section('guidance')
    Muat ulang halaman, masuk kembali bila diperlukan, lalu ulangi
    tindakan Anda. Data yang belum dikirim mungkin perlu diisi kembali.
@endsection