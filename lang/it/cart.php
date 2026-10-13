<?php

return [

    'tax_included' => 'IVA Inclusa',
    'tax_exempt' => 'Esente IVA',
    'tax_name' => 'IVA',

    'coupon_applied' => 'Coupon ":code" applicato con successo.',
    'coupon_removed' => 'Coupon ":code" rimosso.',
    'coupon_already_applied' => 'Il coupon ":code" è già applicato.',
    'coupon_not_found' => 'Coupon ":code" non trovato.',
    'coupon_expired' => 'Il coupon ":code" è scaduto.',
    'coupon_min_amount' => 'Il coupon ":code" richiede un totale minimo di :amount.',
    'coupon_invalid' => 'Il coupon ":code" non è valido.',

    'cart_already_stored' => 'Un carrello con identificativo ":identifier" è già salvato.',
    'stored_cart_undecodable' => 'Il carrello salvato con identificativo ":identifier" non può essere decodificato.',
    'cart_expired' => 'Il carrello è scaduto.',
    'cart_empty' => 'Il carrello è vuoto.',

    'invalid_id' => 'Fornire un identificativo valido.',
    'invalid_name' => 'Fornire un nome valido.',
    'invalid_qty' => 'Fornire una quantità valida. Fornita: :qty',
    'invalid_price' => 'Fornire un prezzo valido.',
    'invalid_row_id' => 'Fornire un row ID valido.',
    'unknown_model' => 'Il modello ":model" non esiste.',

    'limit_exceeded' => 'Limite carrello superato: :type (max: :limit).',
    'limit_max_items' => 'numero massimo articoli',
    'limit_max_qty' => 'quantità massima per articolo',
    'limit_max_total' => 'totale massimo carrello',

    'command_clear_confirm' => 'Questo eliminerà TUTTI i carrelli salvati nel database. Continuare?',
    'command_clear_instance' => 'Questo eliminerà tutti i carrelli per l\'istanza ":instance". Continuare?',
    'command_clear_done' => ':count carrello/i eliminato/i dalla tabella ":table".',
    'command_clear_instance_done' => ':count carrello/i eliminato/i per l\'istanza ":instance".',
    'command_clear_cancelled' => 'Operazione annullata.',

    'command_prune_confirm' => 'Questo eliminerà :count carrello/i più vecchio/i di :hours ore. Continuare?',
    'command_prune_done' => 'Eliminato/i :count carrello/i più vecchio/i di :hours ore.',
    'command_prune_dry_run' => 'Trovato/i :count carrello/i più vecchio/i di :hours ore.',
    'command_prune_empty' => 'Nessun carrello da eliminare.',
    'command_prune_no_records' => 'Nessun record eliminato.',

    'command_export_done' => 'Esportato in :path (:size)',
    'command_export_warning' => 'Esportazione di più di :limit carrelli. Potrebbe richiedere tempo.',
    'command_export_empty' => 'Nessun carrello salvato corrispondente ai criteri.',
    'command_export_dir_not_found' => 'La directory non esiste: :dir',
    'command_export_invalid_date' => 'Formato data non valido: :date. Usare Y-m-d.',

    'command_migrate_done' => ':migrated carrelli migrati. Falliti: :failed. Saltati: :skipped.',
    'command_migrate_error' => ':identifier: :error',
    'command_migrate_confirm' => 'Migrare i dati del carrello da :count sessione/i al database? Continuare?',
    'command_migrate_same_driver' => 'I driver sorgente e destinazione devono essere diversi.',
    'command_migrate_invalid_driver' => 'I driver validi sono: session, database.',
    'command_migrate_no_db_to_session' => 'Non è possibile migrare da database a sessione via CLI. La scrittura in sessione richiede una richiesta HTTP attiva.',
    'command_migrate_unsupported_session' => 'Il driver di sessione ":driver" non è supportato per la migrazione CLI. Solo i driver "database" e "file" possono essere letti da CLI.',
    'command_migrate_dry_run' => 'Trovate :count sessioni da analizzare. Nessun record scritto.',
    'command_migrate_empty' => 'Nessuna sessione trovata.',
    'command_migrate_session_dir_not_found' => 'Directory dei file di sessione non trovata: :path',
    'command_migrate_failed_warning' => ':count carrelli non migrati (vedi sopra per dettagli).',

    'command_inspect_not_found' => 'Nessun carrello trovato per l\'identificativo ":identifier" (istanza: :instance).',
    'command_inspect_error' => 'Impossibile deserializzare il contenuto del carrello. Usa --raw per vedere i dati grezzi.',

    'command_stats_empty' => 'Nessun carrello salvato trovato.',

    'command_list_empty' => 'Nessun carrello salvato trovato.',
    'command_list_footer' => ':shown di :total carrelli salvati.',

];
