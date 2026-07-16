<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChampionshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza a entrada ANTES da validação: aplica trim ao nome do
     * campeonato e a cada nome de time (apenas quando são strings). Assim,
     * duplicidades escondidas por espaços (ex.: "Águias" e " ÁGUIAS ") já
     * chegam normalizadas às regras de validação.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (is_string($this->input('name'))) {
            $normalized['name'] = trim($this->input('name'));
        }

        if (is_array($this->input('teams'))) {
            $normalized['teams'] = array_map(
                fn ($team) => is_string($team) ? trim($team) : $team,
                $this->input('teams'),
            );
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'teams' => ['required', 'array', 'size:8'],
            // distinct:ignore_case rejeita nomes repetidos sem diferenciar
            // maiúsculas/minúsculas (a normalização por trim já foi aplicada).
            'teams.*' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome do campeonato é obrigatório.',
            'name.string' => 'O nome do campeonato deve ser um texto.',
            'name.max' => 'O nome do campeonato não pode ter mais de :max caracteres.',
            'teams.required' => 'A lista de times é obrigatória.',
            'teams.array' => 'Os times devem ser enviados em uma lista.',
            'teams.size' => 'O campeonato deve ter exatamente :size times.',
            'teams.*.required' => 'O nome de cada time é obrigatório.',
            'teams.*.string' => 'Cada nome de time deve ser um texto.',
            'teams.*.max' => 'O nome de cada time não pode ter mais de :max caracteres.',
            'teams.*.distinct' => 'Não é permitido repetir nomes de times (ignorando maiúsculas/minúsculas e espaços).',
        ];
    }
}
