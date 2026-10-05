/**
 * Pegas untuk gerak yang mengikuti jari: bisa diinterupsi, mewarisi
 * kecepatan lepas, dan selalu mulai dari posisi yang sedang tampil.
 *
 * Parameternya memakai bahasa Apple: damping (1 = tanpa pantulan, < 1 =
 * memantul) dan response (detik). `Spring` dari svelte/motion tidak bisa
 * menerima kecepatan awal, padahal itu yang membuat lemparan terasa menyambung.
 */
export type Pegas = { x: number; v: number; raf: number };
export type SetelanPegas = { damping: number; response: number };
export type Sampel = { x: number; t: number };

export function pegas(x = 0): Pegas {
    return { x, v: 0, raf: 0 };
}

export function geraknyaDikurangi(): boolean {
    return (
        window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
    );
}

export function hentikan(p: Pegas): void {
    if (p.raf) {
        cancelAnimationFrame(p.raf);
        p.raf = 0;
    }
}

/** Gerakkan `p.x` ke `ke`, mulai dari posisi dan kecepatan `p` saat ini. */
export function jalankan(
    p: Pegas,
    ke: number,
    setelan: SetelanPegas,
    terapkan: (x: number) => void,
    selesai?: () => void,
): void {
    hentikan(p);

    if (geraknyaDikurangi()) {
        p.x = ke;
        p.v = 0;
        terapkan(ke);
        selesai?.();

        return;
    }

    const kekakuan = ((2 * Math.PI) / setelan.response) ** 2;
    const redaman = (4 * Math.PI * setelan.damping) / setelan.response;
    let terakhir = performance.now();

    const langkah = (sekarang: number): void => {
        const dt = Math.min(0.064, (sekarang - terakhir) / 1000);
        terakhir = sekarang;
        const n = Math.max(1, Math.ceil(dt / 0.004));
        const h = dt / n;

        for (let i = 0; i < n; i++) {
            p.v += (-kekakuan * (p.x - ke) - redaman * p.v) * h;
            p.x += p.v * h;
        }

        if (Math.abs(p.x - ke) < 0.3 && Math.abs(p.v) < 6) {
            p.x = ke;
            p.v = 0;
            p.raf = 0;
            terapkan(ke);
            selesai?.();

            return;
        }

        terapkan(p.x);
        p.raf = requestAnimationFrame(langkah);
    };

    p.raf = requestAnimationFrame(langkah);
}

/** Titik henti sebuah lemparan (fungsi proyeksi dari sampel kode Apple). */
export function proyeksi(v: number, perlambatan = 0.998): number {
    return ((v / 1000) * perlambatan) / (1 - perlambatan);
}

/** Hambatan yang makin besar makin jauh melewati batas. */
export function karet(lewat: number, ukuran: number): number {
    return (lewat * ukuran * 0.55) / (ukuran + 0.55 * Math.abs(lewat));
}

/** Kecepatan (px/detik) dari sampel 100 ms terakhir. */
export function kecepatan(riwayat: Sampel[]): number {
    const akhir = riwayat[riwayat.length - 1];
    const awal =
        riwayat.find((sampel) => akhir.t - sampel.t <= 100) ?? riwayat[0];
    const dt = akhir.t - awal.t;

    return dt < 1 ? 0 : ((akhir.x - awal.x) / dt) * 1000;
}

/**
 * Kurva pegas kritis ternormalisasi untuk transisi Svelte; bentuknya sama
 * dengan --spring di app.css.
 */
export function kurvaPegas(t: number): number {
    return t >= 1 ? 1 : 1 - (1 + 9.3 * t) * Math.exp(-9.3 * t);
}
