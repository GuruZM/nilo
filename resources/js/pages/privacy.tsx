import LegalLayout, { List, Section } from '@/components/legal/legal-layout';

const LAST_UPDATED = '19 July 2026';

export default function Privacy() {
    return (
        <LegalLayout
            title="Privacy Policy"
            lastUpdated={LAST_UPDATED}
            intro="This Privacy Policy explains how Nilo collects, uses, shares, and protects your personal information when you use our invoicing and quotation platform."
        >
            <Section heading="1. Who we are">
                <p>
                    Nilo is an invoicing and quotation platform operated by
                    Resonantt (&ldquo;Nilo&rdquo;, &ldquo;we&rdquo;,
                    &ldquo;us&rdquo; or &ldquo;our&rdquo;). We are the data
                    controller responsible for the personal information
                    described in this policy. This policy applies to your use of
                    the Service and should be read together with our{' '}
                    <a
                        href="/terms"
                        className="font-medium text-brand underline underline-offset-4 hover:text-brand-700"
                    >
                        Terms of Service
                    </a>
                    .
                </p>
            </Section>

            <Section heading="2. Information we collect">
                <p>We collect the following categories of information:</p>
                <List
                    items={[
                        'Account information — your name, email address, password, and profile details you provide when you register or sign in.',
                        'Business and company information — company name, address, tax details, logo, and other details you add to configure your invoices and quotations.',
                        'Client and document data — information you enter about your customers, along with the invoices, quotations, line items, and amounts you create in the Service.',
                        'Payment information — details needed to process your subscription, such as the plan you choose and transaction references. Payment card and mobile-money details are handled by our payment providers, not stored by us directly.',
                        'Usage and technical data — log data, device and browser information, IP address, and how you interact with the Service, collected automatically including through cookies.',
                    ]}
                />
            </Section>

            <Section heading="3. How we use your information">
                <List
                    items={[
                        'To provide, operate, and maintain the Service and your account.',
                        'To generate and manage your invoices, quotations, and client records.',
                        'To process subscriptions, payments, and renewals.',
                        'To communicate with you about your account, updates, security, and support.',
                        'To improve, personalise, and develop the Service.',
                        'To detect, prevent, and address fraud, abuse, and security issues.',
                        'To comply with our legal obligations.',
                    ]}
                />
            </Section>

            <Section heading="4. Sign-in with Google">
                <p>
                    If you choose to sign in using Google, we receive basic
                    profile information from your Google account, such as your
                    name, email address, and profile picture, so that we can
                    create and secure your Nilo account. We only request the
                    information we need to authenticate you, and we do not
                    receive your Google password. Your use of Google sign-in is
                    also subject to Google&rsquo;s own privacy policy and terms.
                </p>
            </Section>

            <Section heading="5. How we share information">
                <p>
                    We do not sell your personal information. We share it only
                    as needed to run the Service:
                </p>
                <List
                    items={[
                        'Service providers — hosting, infrastructure, payment processing, and analytics providers who process data on our behalf under appropriate obligations.',
                        'Authentication providers — such as Google, when you use their sign-in options.',
                        'Legal and safety — where required by law, regulation, or legal process, or to protect the rights, property, and safety of Nilo, our users, or others.',
                        'Business transfers — in connection with a merger, acquisition, or sale of assets, subject to this policy.',
                    ]}
                />
                <p>
                    The client and document data you enter is processed on your
                    behalf to provide the Service. You are responsible for
                    having a lawful basis to store information about your
                    clients.
                </p>
            </Section>

            <Section heading="6. Data retention">
                <p>
                    We retain your personal information for as long as your
                    account is active or as needed to provide the Service, and
                    afterwards only as necessary to comply with our legal
                    obligations, resolve disputes, and enforce our agreements.
                    When information is no longer needed, we take reasonable
                    steps to delete or anonymise it.
                </p>
            </Section>

            <Section heading="7. Data security">
                <p>
                    We use reasonable technical and organisational measures
                    designed to protect your information against unauthorised
                    access, loss, misuse, or alteration. However, no method of
                    transmission or storage is completely secure, and we cannot
                    guarantee absolute security.
                </p>
            </Section>

            <Section heading="8. Your rights">
                <p>
                    Depending on your location and applicable law, you may have
                    the right to access, correct, update, or delete your
                    personal information, to object to or restrict certain
                    processing, and to request a copy of your data. You can
                    manage much of your information directly in your account
                    settings, or contact us to exercise these rights.
                </p>
            </Section>

            <Section heading="9. International data transfers">
                <p>
                    We and our service providers may process and store your
                    information in countries other than your own. Where we
                    transfer personal information across borders, we take steps
                    to ensure it remains protected in accordance with this
                    policy and applicable law.
                </p>
            </Section>

            <Section heading="10. Children's privacy">
                <p>
                    The Service is not intended for individuals under the age of
                    18, and we do not knowingly collect personal information
                    from children. If you believe a child has provided us with
                    personal information, please contact us so we can remove it.
                </p>
            </Section>

            <Section heading="11. Cookies">
                <p>
                    We use cookies and similar technologies to keep you signed
                    in, remember your preferences, secure the Service, and
                    understand how it is used. You can control cookies through
                    your browser settings, though disabling some cookies may
                    affect how the Service works.
                </p>
            </Section>

            <Section heading="12. Changes to this policy">
                <p>
                    We may update this Privacy Policy from time to time. When we
                    make material changes, we will update the &ldquo;Last
                    updated&rdquo; date above and, where appropriate, notify
                    you. Your continued use of the Service after the changes
                    take effect constitutes acceptance of the updated policy.
                </p>
            </Section>

            <Section heading="13. Contact us">
                <p>
                    If you have any questions about this Privacy Policy or how
                    we handle your information, please contact us at{' '}
                    <a
                        href="mailto:privacy@nilo.resonantt.com"
                        className="font-medium text-brand underline underline-offset-4 hover:text-brand-700"
                    >
                        privacy@nilo.resonantt.com
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
