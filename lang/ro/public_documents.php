<?php

return [
    'management' => [
        'title' => 'Link public securizat',
        'description' => 'Partajează documentul curent fără a expune identificatorii companiei sau ai documentului.',
        'statuses' => [
            'ACTIVE' => 'Activ',
            'DISABLED' => 'Dezactivat',
            'EXPIRED' => 'Expirat',
            'NOT_CREATED' => 'Necreat',
        ],
        'expires' => 'Expiră: :date',
        'copy' => 'Copiază linkul',
        'copied' => 'Linkul securizat a fost copiat.',
        'copy_failed' => 'Linkul securizat nu a putut fi copiat.',
        'create' => 'Creează link securizat',
        're_enable' => 'Creează un link securizat nou',
        'regenerate' => 'Regenerează linkul',
        'revoke' => 'Revocă linkul',
    ],
    'page' => [
        'head_title' => 'Document partajat',
        'description' => 'Acesta este documentul partajat în forma sa curentă.',
        'download_pdf' => 'Descarcă PDF-ul',
        'provided_by' => 'Partajat securizat cu Invumo',
    ],
    'feedback' => [
        'created' => 'Linkul public securizat a fost creat.',
        'regenerated' => 'Linkul public securizat a fost regenerat.',
        'revoked' => 'Linkul public securizat a fost revocat.',
    ],
    'errors' => [
        'unavailable' => 'Linkul public securizat nu mai este disponibil. Creează mai întâi un link nou.',
    ],
];
