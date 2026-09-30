@extends('layouts.app')

@section('title', 'Раздел в разработке')

@section('content')
    <h1 class="page-title">Раздел в разработке</h1>
    <div class="content-text">
        <p>Этот раздел появится в ближайших обновлениях сайта.</p>
        <p><a href="{{ route('home') }}">Вернуться на главную</a></p>
    </div>
@endsection
