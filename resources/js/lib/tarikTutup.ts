import type { Action } from 'svelte/action';
import {
    hentikan,
    jalankan,
    karet,
    kecepatan,
    pegas,
    proyeksi,
} from '@/lib/pegas';
import type { Sampel } from '@/lib/pegas';

type Opsi = { aktif: boolean; onTutup: () => void };

type Tarikan = {
    id: number;
    awalY: number;
    acuan: number;
    aktif: boolean;
    riwayat: Sampel[];
};

/**
 * Bottom sheet yang ditarik turun untuk ditutup.
 *
 * Hanya dari elemen bertanda data-tarik (grabber dan judul) supaya isi yang
 * bisa digulir tetap menggulir. Panel menempel 1:1 di jari. Saat dilepas,
 * lemparannya diproyeksikan: lewat 40% tinggi panel berarti tutup (transisi
 * keluar mulai dari posisi jari), kurang dari itu panel memantul balik
 * membawa kecepatan jari.
 */
export const tarikTutup: Action<HTMLElement, Opsi> = (node, awal) => {
    let opsi = awal;
    const p = pegas();
    let tarikan: Tarikan | null = null;

    const terapkan = (y: number): void => {
        node.style.transform = y === 0 ? '' : `translate3d(0, ${y}px, 0)`;
    };

    const turun = (event: PointerEvent): void => {
        const target = event.target as Element | null;

        if (
            !opsi.aktif ||
            event.button !== 0 ||
            !target?.closest('[data-tarik]')
        ) {
            return;
        }

        hentikan(p);
        tarikan = {
            id: event.pointerId,
            awalY: event.clientY,
            acuan: event.clientY - p.x,
            aktif: false,
            riwayat: [{ x: p.x, t: performance.now() }],
        };
    };

    const gerak = (event: PointerEvent): void => {
        if (!tarikan || event.pointerId !== tarikan.id) {
            return;
        }

        if (!tarikan.aktif) {
            if (Math.abs(event.clientY - tarikan.awalY) < 6) {
                return;
            }

            tarikan.aktif = true;
            tarikan.acuan = event.clientY - p.x;
            node.setPointerCapture(event.pointerId);
        }

        const mentah = event.clientY - tarikan.acuan;
        // Ke atas tidak ada apa-apa lagi: karet, bukan berhenti mati.
        p.x = mentah < 0 ? -karet(-mentah, node.offsetHeight) : mentah;
        terapkan(p.x);
        tarikan.riwayat.push({ x: p.x, t: performance.now() });

        if (tarikan.riwayat.length > 8) {
            tarikan.riwayat.shift();
        }
    };

    const naik = (event: PointerEvent): void => {
        if (!tarikan || event.pointerId !== tarikan.id) {
            return;
        }

        const selesai = tarikan;
        tarikan = null;

        if (!selesai.aktif) {
            return;
        }

        const v = kecepatan(selesai.riwayat);

        if (p.x + proyeksi(v, 0.99) > node.offsetHeight * 0.4) {
            opsi.onTutup();

            return;
        }

        p.v = v;
        jalankan(p, 0, { damping: 0.8, response: 0.35 }, terapkan);
    };

    node.addEventListener('pointerdown', turun);
    node.addEventListener('pointermove', gerak);
    node.addEventListener('pointerup', naik);
    node.addEventListener('pointercancel', naik);

    return {
        update(baru) {
            opsi = baru;
        },
        destroy() {
            hentikan(p);
            node.removeEventListener('pointerdown', turun);
            node.removeEventListener('pointermove', gerak);
            node.removeEventListener('pointerup', naik);
            node.removeEventListener('pointercancel', naik);
        },
    };
};
