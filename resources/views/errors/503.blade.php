@extends('errors.layout')

@section('code', '503')

@section('title', 'Layanan sedang tidak tersedia')

@section('status', 'Pemeliharaan atau gangguan sementara')

@section('message')
    SchoolSafe sedang menjalani pemeliharaan atau mengalami gangguan
    sementara sehingga layanan belum dapat digunakan.
@endsection

@section('guidance')
    Silakan mencoba kembali beberapa saat lagi. Tim pengelola sedang
    memastikan layanan dapat digunakan kembali dengan aman.
@endsection