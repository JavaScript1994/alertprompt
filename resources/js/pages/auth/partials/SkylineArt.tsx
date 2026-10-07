import { cn } from '@/lib/utils';

/** Silueta de Lima en línea fina (puente, edificios, catedral). Decorativa. */
export default function SkylineArt({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 640 120"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.25"
            strokeLinejoin="round"
            preserveAspectRatio="none"
            aria-hidden
            className={cn('w-full [&_path]:[vector-effect:non-scaling-stroke]', className)}
        >
            {/* Puente atirantado */}
            <path d="M0 108h120M28 108V58M92 108V58M28 58L4 108M28 58l24 50M92 58L68 108M92 58l24 50M28 58l-14 50M92 58l14 50" />
            {/* Edificios */}
            <path d="M136 120V70h18v50M140 78h10M140 88h10M140 98h10" />
            <path d="M160 120V40l22-10v90M166 50h10M166 62h10M166 74h10M166 86h10M166 98h10" />
            <path d="M190 120V78h24v42" />
            <path d="M220 120V92h14v28" />
            <path d="M240 120V60h12l6-14 6 14h6v60" />
            {/* Catedral */}
            <path d="M282 120V82h10v-8a8 8 0 0 1 16 0v8h28v-8a8 8 0 0 1 16 0v8h10v38M314 120v-22a8 8 0 0 1 16 0v22" />
            <path d="M372 120V48h20v72M376 58h12M376 70h12M376 82h12M376 94h12M376 106h12" />
            <path d="M398 120V84h16v36" />
            <path d="M420 120V56l10-14 10 14v64M424 66h12M424 80h12M424 94h12" />
            <path d="M448 120V30h18v90M452 40h10M452 52h10M452 64h10M452 76h10M452 88h10M452 100h10" />
            <path d="M472 120V90h20v30" />
            <path d="M498 120V50q12-22 24 0v70" />
            <path d="M530 120V98h18v22" />
            <path d="M556 120V64h16v56" />
            <path d="M578 120V36l18 10v74" />
            <path d="M602 120V86h30v34" />
            <path d="M0 120h640" />
        </svg>
    );
}
