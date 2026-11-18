@extends('plataforma.impressao.base')

@section('conteudo')
    <table>
        @foreach ($dados as [$rotulo, $valor])
            <tr>
                <th style="width: 30%;">{{ $rotulo }}</th>
                <td>{{ $valor }}</td>
            </tr>
        @endforeach
    </table>
@endsection
