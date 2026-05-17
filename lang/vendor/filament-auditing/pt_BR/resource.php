<?php

return [
    'title' => 'Histórico de Auditoria',
    'audit_relation_title' => 'Histórico',
    'owner_relation_title' => 'Auditoria',
    'record_title' => 'Auditoria - :record (:id) @ :timestamp',
    'actions' => [
        'restore_audit' => [
            'label' => 'Restaurar',
            'restore_to_old' => 'Restaurar para valores anteriores?',
            'restore_from_values' => 'Restaurando dos Valores',
            'restore_to_values' => 'Restaurando para os Valores',
        ],
        'view' => [
            'auditable' => 'Visualizar registro',
            'owner' => 'Visualizar proprietário',
            'title' => 'Visualizar :title',
        ],
    ],
    'fields' => [
        'auditable_type' => 'Tipo de registro',
        'audited_fields' => 'Campos modificados',
        'created_at' => 'Registrado em',
        'created_at_since' => 'Registrado',
        'event' => 'Evento',
        'ip_address' => 'Endereço IP',
        'query' => 'Avançado',
        'tags' => 'Tags',
        'url' => 'URL',
        'user_agent' => 'Agente de usuário',
        'user' => [
            'label' => 'Proprietário',
            'type_label' => 'Tipo',
            'id' => 'ID',
            'email' => 'E-mail',
            'summary' => [
                'type_direct' => ':relationship é :type',
                'type_inverse' => ':relationship não é :type',
                'value_direct' => ':relationship (:type) é :value',
                'value_inverse' => ':relationship (:type) não é :value',
            ],
        ],
        'id' => 'ID',
    ],
    'tabs' => [
        'label' => 'Dados de auditoria',
        'meta' => 'Metadados',
        'new' => 'Novo (Após)',
        'old' => 'Anterior (Antes)',
        'user' => 'Dados do usuário',
    ],
];
