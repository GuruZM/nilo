import LegalLayout, { List, Section } from '@/components/legal/legal-layout';

const LAST_UPDATED = '19 July 2026';

export default function Terms() {
    return (
        <LegalLayout
            title="Terms of Service"
            lastUpdated={LAST_UPDATED}
            intro="These Terms of Service govern your access to and use of Nilo, an invoicing and quotation platform for startups and small and medium-sized businesses. Please read them carefully before using the service."
        >
            <Section heading="1. Agreement to these Terms">
                <p>
                    Nilo (&ldquo;Nilo&rdquo;, the &ldquo;Service&rdquo;,
                    &ldquo;we&rdquo;, &ldquo;us&rdquo; or &ldquo;our&rdquo;) is
                    operated by Resonantt. By creating an account, accessing, or
                    using the Service, you agree to be bound by these Terms of
                    Service (the &ldquo;Terms&rdquo;) and by our{' '}
                    <a
                        href="/privacy"
                        className="font-medium text-brand underline underline-offset-4 hover:text-brand-700"
                    >
                        Privacy Policy
                    </a>
                    . If you do not agree to these Terms, you may not use the
                    Service.
                </p>
                <p>
                    If you are using the Service on behalf of a business or
                    other legal entity, you represent that you have the
                    authority to bind that entity to these Terms, in which case
                    &ldquo;you&rdquo; refers to that entity.
                </p>
            </Section>

            <Section heading="2. Eligibility">
                <p>
                    You must be at least 18 years old, or the age of majority in
                    your jurisdiction, and capable of forming a binding contract
                    to use the Service. By using Nilo you confirm that you meet
                    these requirements and that the information you provide is
                    accurate and complete.
                </p>
            </Section>

            <Section heading="3. Your account">
                <List
                    items={[
                        'You are responsible for maintaining the confidentiality of your login credentials and for all activity that occurs under your account.',
                        'You must notify us promptly of any unauthorised use of your account or any other breach of security.',
                        'You are responsible for the accuracy of the business, company, and client information you enter into the Service.',
                        'We may offer sign-in through third-party providers such as Google. Your use of those sign-in options is also subject to the third party’s terms.',
                    ]}
                />
            </Section>

            <Section heading="4. Subscriptions, plans and billing">
                <p>
                    Nilo is offered on both free and paid subscription plans.
                    Certain features are only available on paid plans and, where
                    offered, may include a free trial period.
                </p>
                <List
                    items={[
                        'Paid plans are billed in advance on a recurring basis (for example monthly or annually) at the price shown at the time of purchase.',
                        'Payments may be made through supported payment methods, including mobile money and other channels. Some payments are confirmed manually before your subscription is activated or renewed.',
                        'Unless required by law, fees already paid are non-refundable, including for partial billing periods and unused features.',
                        'We may change our plans, features, and pricing. Where a change affects your active subscription, we will give you reasonable notice before it takes effect.',
                        'If a payment fails or cannot be confirmed, we may suspend or downgrade your access to paid features until payment is received.',
                    ]}
                />
                <p>
                    You may cancel your subscription at any time. Cancellation
                    takes effect at the end of the current billing period, and
                    you will retain access to paid features until then.
                </p>
            </Section>

            <Section heading="5. Your content and data">
                <p>
                    You retain all ownership rights to the invoices, quotations,
                    client records, company details, logos, and other content
                    you create or upload through the Service (&ldquo;Your
                    Content&rdquo;). We do not claim ownership of Your Content.
                </p>
                <p>
                    You grant us a limited licence to host, store, process, and
                    display Your Content solely to operate and provide the
                    Service to you. You are responsible for ensuring that Your
                    Content, and your use of it, complies with applicable law,
                    including any consent required to store information about
                    your clients.
                </p>
            </Section>

            <Section heading="6. Acceptable use">
                <p>You agree not to use the Service to:</p>
                <List
                    items={[
                        'Break any applicable law or regulation, or infringe the rights of others.',
                        'Issue fraudulent, misleading, or unauthorised invoices or quotations.',
                        'Upload malicious code, or attempt to gain unauthorised access to the Service or other users’ data.',
                        'Interfere with, disrupt, or place an unreasonable load on the Service or its infrastructure.',
                        'Resell, sublicense, or provide the Service to third parties except as expressly permitted.',
                    ]}
                />
            </Section>

            <Section heading="7. Intellectual property">
                <p>
                    The Service, including its software, design, branding, and
                    all related intellectual property, is owned by Resonantt and
                    its licensors and is protected by law. Except for the rights
                    expressly granted to you in these Terms, we reserve all
                    rights in the Service. You may not copy, modify, distribute,
                    or create derivative works from the Service without our
                    prior written permission.
                </p>
            </Section>

            <Section heading="8. Third-party services">
                <p>
                    The Service may integrate with or rely on third-party
                    services, such as payment providers, hosting providers, and
                    authentication providers. We are not responsible for the
                    availability, accuracy, or practices of those third parties,
                    and your use of them may be subject to their own terms and
                    policies.
                </p>
            </Section>

            <Section heading="9. Service availability">
                <p>
                    We work to keep the Service available and reliable, but we
                    do not guarantee that it will be uninterrupted, error-free,
                    or secure at all times. We may modify, suspend, or
                    discontinue any part of the Service, and we may perform
                    maintenance that temporarily limits availability.
                </p>
            </Section>

            <Section heading="10. Disclaimers">
                <p>
                    The Service is provided on an &ldquo;as is&rdquo; and
                    &ldquo;as available&rdquo; basis, without warranties of any
                    kind, whether express or implied, to the fullest extent
                    permitted by law. Nilo is a tool to help you create and
                    manage invoices and quotations; it does not provide legal,
                    tax, or accounting advice, and you remain responsible for
                    the accuracy and legality of the documents you produce.
                </p>
            </Section>

            <Section heading="11. Limitation of liability">
                <p>
                    To the fullest extent permitted by law, Resonantt and its
                    officers, employees, and partners will not be liable for any
                    indirect, incidental, special, consequential, or punitive
                    damages, or for any loss of profits, revenue, data, or
                    business, arising out of or in connection with your use of
                    the Service. Our total liability for any claim relating to
                    the Service will not exceed the amount you paid to us for
                    the Service in the twelve months preceding the event giving
                    rise to the claim.
                </p>
            </Section>

            <Section heading="12. Indemnification">
                <p>
                    You agree to indemnify and hold harmless Resonantt from any
                    claims, liabilities, damages, and expenses (including
                    reasonable legal fees) arising from Your Content, your use
                    of the Service, or your breach of these Terms or of
                    applicable law.
                </p>
            </Section>

            <Section heading="13. Suspension and termination">
                <p>
                    We may suspend or terminate your access to the Service if
                    you breach these Terms, if required by law, or to protect
                    the Service or other users. You may stop using the Service
                    and close your account at any time. On termination, your
                    right to use the Service ends, and we may delete Your
                    Content in accordance with our{' '}
                    <a
                        href="/privacy"
                        className="font-medium text-brand underline underline-offset-4 hover:text-brand-700"
                    >
                        Privacy Policy
                    </a>{' '}
                    and applicable law. Provisions that by their nature should
                    survive termination will continue to apply.
                </p>
            </Section>

            <Section heading="14. Changes to these Terms">
                <p>
                    We may update these Terms from time to time. When we make
                    material changes, we will update the &ldquo;Last
                    updated&rdquo; date above and, where appropriate, notify
                    you. Your continued use of the Service after the changes
                    take effect constitutes acceptance of the updated Terms.
                </p>
            </Section>

            <Section heading="15. Governing law">
                <p>
                    These Terms are governed by and construed in accordance with
                    the laws of Zambia, without regard to conflict-of-law
                    principles. You agree that the courts of Zambia will have
                    jurisdiction over any dispute arising out of or relating to
                    these Terms or the Service.
                </p>
            </Section>

            <Section heading="16. Contact us">
                <p>
                    If you have any questions about these Terms, please contact
                    us at{' '}
                    <a
                        href="mailto:legal@nilo.resonantt.com"
                        className="font-medium text-brand underline underline-offset-4 hover:text-brand-700"
                    >
                        legal@nilo.resonantt.com
                    </a>{' '}
                    or by phone at{' '}
                    <a
                        href="tel:+260770785275"
                        className="font-medium text-brand underline underline-offset-4 hover:text-brand-700"
                    >
                        +260 77 078 5275
                    </a>
                    .
                </p>
            </Section>
        </LegalLayout>
    );
}
