@extends('plataforma.impressao.base')

@section('conteudo')
    @if ($corpo)
        {!! $corpo !!}
    @else
        <table>
            @foreach ($dados as [$rotulo, $valor])
                <tr>
                    <th style="width: 30%;">{{ $rotulo }}</th>
                    <td>{{ $valor }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endsection
