<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gerador de placar (script Python)
    |--------------------------------------------------------------------------
    |
    | Binário do Python e timeout (em segundos) usados para executar o script
    | externo que gera os placares. O caminho do script é fixo (base_path) e
    | nunca vem da requisição HTTP.
    |
    */

    'python_binary' => env('PYTHON_BINARY', 'python3'),

    'process_timeout' => (int) env('PYTHON_PROCESS_TIMEOUT', 5),

    'script_path' => base_path('teste.py'),

];
