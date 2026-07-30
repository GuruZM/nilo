{{--
    The masthead of a client-facing document email. It carries the company's own
    mark only — never the platform's. Embedding beats linking when the file is
    local, since embedded images survive mail clients that block remote content.
--}}
<tr>
    <td style="background-color:#ffffff;border-radius:16px 16px 0 0;border-top:1px solid #d8e7f5;border-left:1px solid #d8e7f5;border-right:1px solid #d8e7f5;padding:32px 40px 24px;">
        @if(!empty($companyLogoEmbedPath) && isset($message))
            <img src="{{ $message->embed($companyLogoEmbedPath) }}" alt="{{ $companyName }}" style="display:block;border:0;max-width:180px;max-height:56px;height:auto;line-height:100%;outline:none;text-decoration:none;">
        @elseif(!empty($companyLogoUrl))
            <img src="{{ $companyLogoUrl }}" alt="{{ $companyName }}" style="display:block;border:0;max-width:180px;max-height:56px;height:auto;line-height:100%;outline:none;text-decoration:none;">
        @elseif(!empty($companyName))
            <span class="email-heading" style="display:block;color:#001d3a;font-size:20px;font-weight:700;letter-spacing:-0.01em;">{{ $companyName }}</span>
        @endif
    </td>
</tr>
