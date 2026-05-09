export const sk = {
    nav: {
        home: 'Domov',
        console: 'Konzola',
        pendulum: 'Inverzné kyvadlo',
        ballBeam: 'Gulička na tyči',
        logs: 'Logy',
        apiDocs: 'API dokumentácia',
        stats: 'Štatistika',
    },
    common: {
        loading: 'Načítava sa…',
        close: 'Zavrieť',
        cancel: 'Zrušiť',
        confirm: 'Potvrdiť',
        save: 'Uložiť',
        submit: 'Odoslať',
        back: 'Späť',
        next: 'Ďalej',
        previous: 'Predchádzajúce',
        skipToContent: 'Preskočiť na obsah',
        toggleTheme: 'Prepnúť motív',
        switchLanguage: 'Prepnúť jazyk',
        openMenu: 'Otvoriť menu',
        closeMenu: 'Zavrieť menu',
    },
    theme: {
        light: 'Svetlý',
        dark: 'Tmavý',
    },
    home: {
        title: 'WEBTE2 — Octave CAS',
        subtitle: 'Webový front-end pre GNU Octave: konzola, simulácie, štatistiky.',
    },
    console: {
        title: 'Octave konzola',
        subtitle: 'Interaktívne spúšťanie príkazov v perzistentnom workspace.',
    },
    pendulum: {
        title: 'Inverzné kyvadlo',
        subtitle: 'Animovaná simulácia s grafom stavov.',
    },
    ballBeam: {
        title: 'Gulička na tyči',
        subtitle: 'Animovaná simulácia s grafom stavov.',
    },
    logs: {
        title: 'Logy požiadaviek',
        subtitle: 'História API volaní s exportom do CSV.',
    },
    apiDocs: {
        title: 'API dokumentácia',
        subtitle: 'OpenAPI špecifikácia v prehliadači aj na stiahnutie ako PDF.',
    },
    stats: {
        title: 'Štatistika použitia',
        subtitle: 'Anonymné metriky o použití animácií.',
    },
    footer: {
        copyright: 'WEBTE2 — záverečné zadanie',
    },
    errors: {
        required: 'Toto pole je povinné',
        invalid: 'Neplatná hodnota',
    },
} as const;

export type Translation = typeof sk;
