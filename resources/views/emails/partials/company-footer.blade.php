{{--
    Signs a client-facing document email off with the company's own details.
    Every line is optional, so a company that has only filled in a name gets a
    footer with just that.
--}}
@php
    $contactLine = array_filter([$companyEmail ?? null, $companyPhone ?? null]);
@endphp
<tr>
    <td style="background-color:#001d3a;border-radius:0 0 16px 16px;padding:24px 40px;">
        @if(!empty($companyName))
            <p style="margin:0 0 6px;color:#ffffff;font-size:13px;font-weight:600;">
                {{ $companyName }}
            </p>
        @endif

        @if($contactLine !== [])
            <p style="margin:0 0 2px;color:rgba(255,255,255,0.6);font-size:12px;">
                {{ implode(' · ', $contactLine) }}
            </p>
        @endif

        @if(!empty($companyAddress))
            <p style="margin:0;color:rgba(255,255,255,0.4);font-size:12px;">
                {{ $companyAddress }}
            </p>
        @endif
    </td>
</tr>
