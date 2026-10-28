<?php

namespace App\Enrolment\V2;

use App\Models\Ams2Enrolment;

/**
 * Builds exactly what the enrol step WOULD send, without sending it.
 *
 * For inspecting a payload before enabling the processor against live RTO
 * Data. It makes no HTTP call and changes no status — nothing here may have a
 * side effect.
 *
 * The public key is masked: a dry run is something you paste into a ticket.
 */
class Ams2DryRun
{
    public const MASK = '***masked***';

    public function __construct(
        private Ams2EnrolArgs $args,
    ) {}

    /**
     * Rows the enrol step would pick up on the next run.
     *
     * @return \Illuminate\Support\Collection<int, Ams2Enrolment>
     */
    public function eligibleRows()
    {
        return Ams2Enrolment::query()
            ->where('status', Ams2Status::RECEIVED)
            ->orderBy('id')
            ->limit(Ams2Processor::BATCH_LIMIT)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(Ams2Enrolment $row): array
    {
        $args = $this->args->build($row);

        return [
            'row' => $row->displayRef(),
            'id' => $row->id,
            'status' => $row->status,
            'endpoint' => rtrim((string) config('services.enrolment_api.base_url'), '/').'/'.Ams2ApiClient::ENROL,
            'missing_required' => $this->args->missingRequired($args),
            'payload' => [
                'public_key' => self::MASK,
                'subdomain' => config('services.enrolment_api.subdomain', 'amstraining'),
                /*
                 * Sent as a single urlencoded "values" field. Shown decoded
                 * here because the point of a dry run is to read it.
                 */
                'values' => $args,
            ],
        ];
    }

    public function toJson(Ams2Enrolment $row): string
    {
        return json_encode(
            $this->describe($row),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
