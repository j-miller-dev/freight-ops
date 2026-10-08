type Props = {
    cls: string;
    size?: number;
};

type Style = { fill: string; text: string };

// Approximations of the ADG class labels, enough to tell classes apart at a
// glance. Class 8 is drawn half white, half black like the real label.
const STYLES: Record<string, Style> = {
    '1': { fill: '#f97316', text: '#000' },
    '2.1': { fill: '#dc2626', text: '#fff' },
    '2.2': { fill: '#16a34a', text: '#fff' },
    '2.3': { fill: '#ffffff', text: '#000' },
    '3': { fill: '#dc2626', text: '#fff' },
    '4.1': { fill: '#ffffff', text: '#000' },
    '4.2': { fill: '#ffffff', text: '#000' },
    '4.3': { fill: '#2563eb', text: '#fff' },
    '5.1': { fill: '#facc15', text: '#000' },
    '5.2': { fill: '#facc15', text: '#000' },
    '6.1': { fill: '#ffffff', text: '#000' },
    '6.2': { fill: '#ffffff', text: '#000' },
    '7': { fill: '#facc15', text: '#000' },
    '9': { fill: '#ffffff', text: '#000' },
};

export default function DgDiamond({ cls, size = 40 }: Props) {
    const style = STYLES[cls] ?? { fill: '#ffffff', text: '#000' };
    const corrosive = cls === '8';

    return (
        <svg
            role="img"
            aria-label={`Dangerous goods class ${cls}`}
            viewBox="0 0 48 48"
            width={size}
            height={size}
            className="shrink-0"
        >
            <polygon
                points="24,2 46,24 24,46 2,24"
                fill={corrosive ? '#ffffff' : style.fill}
                stroke="#111827"
                strokeWidth="2"
                strokeLinejoin="round"
            />
            {corrosive && (
                <polygon points="3.5,25 44.5,25 24,45" fill="#111827" />
            )}
            <text
                x="24"
                y={corrosive ? 40 : 29}
                textAnchor="middle"
                fontSize={cls.length > 1 ? 13 : 16}
                fontWeight="700"
                fontFamily="system-ui, sans-serif"
                fill={corrosive ? '#ffffff' : style.text}
            >
                {cls}
            </text>
        </svg>
    );
}
