export type ImageCredit = {
    title: string;
    author: string;
    source_url: string;
    license: string;
    license_url: string | null;
    modifications: string;
};

const creditLink =
    'underline underline-offset-2 hover:text-stone-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900';

// The licences of the bundled Wikimedia Commons photographs ask for title, author, source, licence and changes.
export function PhotoCredit({ credit, cropped = false }: { credit: ImageCredit; cropped?: boolean }) {
    return (
        <>
            Photo:{' '}
            <a href={credit.source_url} className={creditLink}>
                {credit.title}
            </a>{' '}
            by {credit.author}, Wikimedia Commons,{' '}
            {credit.license_url ? (
                <a href={credit.license_url} rel="license" className={creditLink}>
                    {credit.license}
                </a>
            ) : (
                credit.license
            )}
            . {credit.modifications}
            {cropped && ' This page shows it cropped to fit the layout.'}
        </>
    );
}
