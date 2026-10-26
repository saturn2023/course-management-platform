<?php

namespace App\Enrolment\V2;

/**
 * The document types an Enrolment v2 submission can carry.
 *
 * Port of ams2_doc_types(). The keys are what `docs_expected` lists and what
 * `ams2_enrolment_documents.doc_type` stores; the labels are what an operator
 * reads in an alert about a document that did not arrive.
 */
final class Ams2DocumentTypes
{
    public const ID_DOCUMENT = 'id_document';

    public const VET_TRANSCRIPT = 'vet_transcript';

    /**
     * @return array<string, array{file: string, label: string}>
     */
    public static function all(): array
    {
        return [
            self::ID_DOCUMENT => ['file' => 'ID-Document', 'label' => 'ID document'],
            self::VET_TRANSCRIPT => ['file' => 'VET-Transcript', 'label' => 'VET transcript'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function label(string $type): string
    {
        return self::all()[$type]['label'] ?? $type;
    }
}
