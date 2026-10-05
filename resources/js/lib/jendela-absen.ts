export type JadwalHariIni = {
    jam_masuk: string;
    jam_pulang: string;
    buka_masuk: string;
    tutup_masuk: string;
    buka_pulang: string;
    is_hari_kerja: boolean;
};

export type StatusJendela =
    | { bisa: true; konfirmasiPulangCepat: boolean }
    | { bisa: false; judul: string; pesan: string; sarankanIzin?: boolean };

/**
 * Jam dinding server, dalam menit sejak tengah malam.
 *
 * Dihitung dari selisih jam HP dengan waktuServer saat halaman dimuat, dan
 * dibaca dalam zona waktu server (offset di string ISO-nya). Jadi guru di
 * zona lain atau dengan jam HP yang meleset tetap melihat jendela yang sama
 * dengan yang dinilai server.
 */
export function jamServer(
    waktuServer: string,
    selisihMs: number,
    sekarangMs: number = Date.now(),
): number {
    const cocok = /([+-])(\d{2}):(\d{2})$/.exec(waktuServer);
    const offsetMenit = cocok
        ? (cocok[1] === '-' ? -1 : 1) *
          (Number(cocok[2]) * 60 + Number(cocok[3]))
        : 0;
    const menitUtc = Math.floor((sekarangMs + selisihMs) / 60_000);

    return (((menitUtc + offsetMenit) % 1440) + 1440) % 1440;
}

function menit(jam: string): number {
    const [h, m] = jam.split(':').map(Number);

    return h * 60 + m;
}

/**
 * Apakah tombol absen boleh ditekan sekarang, dan kalau tidak, kenapa.
 *
 * Cermin aturan CatatAbsensi::diLuarJendela di server -- server tetap
 * penentu akhir; ini supaya guru tidak menunggu GPS dan sidik jari hanya
 * untuk ditolak.
 */
export function statusJendela(
    jadwal: JadwalHariIni | null,
    libur: string | null,
    sudahMasuk: boolean,
    sekarang: number,
): StatusJendela {
    if (libur) {
        return {
            bisa: false,
            judul: 'Hari ini libur',
            pesan: `${libur}. Tidak perlu absen.`,
        };
    }

    if (jadwal === null) {
        return { bisa: true, konfirmasiPulangCepat: false };
    }

    if (!jadwal.is_hari_kerja) {
        return {
            bisa: false,
            judul: 'Bukan hari kerja',
            pesan: 'Hari ini bukan hari kerjamu, jadi tidak perlu absen.',
        };
    }

    if (!sudahMasuk) {
        if (sekarang < menit(jadwal.buka_masuk)) {
            return {
                bisa: false,
                judul: 'Absen masuk belum dibuka',
                pesan: `Absen masuk dibuka pukul ${jadwal.buka_masuk}.`,
            };
        }

        // >= karena server menolak begitu lewat detik ke-0 menit tutup.
        if (sekarang >= menit(jadwal.tutup_masuk)) {
            return {
                bisa: false,
                judul: 'Absen masuk sudah ditutup',
                pesan: `Ditutup pukul ${jadwal.tutup_masuk}. Kalau kamu hadir, lapor ke TU. Kalau berhalangan, ajukan izin.`,
                sarankanIzin: true,
            };
        }

        return { bisa: true, konfirmasiPulangCepat: false };
    }

    if (sekarang < menit(jadwal.buka_pulang)) {
        return {
            bisa: false,
            judul: 'Absen pulang belum dibuka',
            pesan: `Absen pulang dibuka pukul ${jadwal.buka_pulang}.`,
        };
    }

    return {
        bisa: true,
        konfirmasiPulangCepat: sekarang < menit(jadwal.jam_pulang),
    };
}

function jam(menitHari: number): string {
    const h = Math.floor(menitHari / 60);
    const m = menitHari % 60;

    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

export type SegmenJendela = {
    /** Batas tiap segmen dan posisi sekarang, dalam persen panjang garis. */
    tepatSampai: number;
    terlambatSampai: number;
    pulangMulai: number;
    sekarang: number;
    batasTepat: string;
    keterangan: string;
    nada: 'tepat' | 'telat' | 'netral';
};

/**
 * Garis jendela absen di beranda guru: dari buka absen masuk sampai
 * setengah jam setelah jam pulang.
 *
 * Batas tepat waktu = jam masuk + toleransi, sama dengan
 * CatatAbsensi::statusMasuk (lewat dari menit itu tercatat terlambat).
 */
export function segmenJendela(
    jadwal: JadwalHariIni,
    toleransi: number,
    sudahMasuk: boolean,
    sekarang: number,
): SegmenJendela {
    const awal = menit(jadwal.buka_masuk);
    const akhir = menit(jadwal.jam_pulang) + 30;
    const batas = menit(jadwal.jam_masuk) + toleransi;
    const tutup = menit(jadwal.tutup_masuk);
    const pulang = menit(jadwal.buka_pulang);
    const persen = (m: number): number =>
        Math.max(0, Math.min(100, ((m - awal) / (akhir - awal)) * 100));

    let keterangan: string;
    let nada: SegmenJendela['nada'] = 'netral';

    if (!sudahMasuk && sekarang >= tutup) {
        keterangan = `Absen masuk ditutup ${jadwal.tutup_masuk}`;
        nada = 'telat';
    } else if (sekarang < awal) {
        keterangan = `Absen masuk buka ${jadwal.buka_masuk}`;
    } else if (sudahMasuk && sekarang < pulang) {
        keterangan = `Absen pulang buka ${jadwal.buka_pulang}`;
    } else if (sekarang < batas) {
        keterangan = `${batas - sekarang} mnt lagi batas tepat`;
        nada = 'tepat';
    } else if (sekarang < tutup) {
        keterangan = `Terlambat · ditutup ${jadwal.tutup_masuk}`;
        nada = 'telat';
    } else if (sekarang < pulang) {
        keterangan = `Absen pulang buka ${jadwal.buka_pulang}`;
    } else {
        keterangan = 'Absen pulang sudah dibuka';
    }

    return {
        tepatSampai: persen(batas),
        terlambatSampai: persen(tutup),
        pulangMulai: persen(pulang),
        sekarang: persen(sekarang),
        batasTepat: jam(batas),
        keterangan,
        nada,
    };
}
