@extends('plataforma.impressao.base')

@section('conteudo')
    @if ($filtros)
        <div class="filtros">{{ $filtros }}</div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach ($cabecalhos as $cabecalho_coluna)
                    <th>{{ $cabecalho_coluna }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $linha)
                <tr>
                    @foreach ($linha as $valor)
                        <td>{{ $valor }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($cabecalhos) }}">Nenhum registro.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p style="margin-top: 6px;">{{ count($linhas) }} registro(s)</p>
@endsection
