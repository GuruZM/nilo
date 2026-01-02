import { ResponsiveContainer, BarChart, Bar, XAxis, Tooltip } from 'recharts';

interface MiniBarProps {
  data: { name: string; value: number }[];
  color?: string;
  height?: number;
}

export function MiniBar({ data, color = '#00417d', height = 36 }: MiniBarProps) {
  return (
    <ResponsiveContainer width="100%" height={height}>
      <BarChart data={data} margin={{ top: 0, right: 0, left: 0, bottom: 0 }}>
        <XAxis dataKey="name" hide />
        <Tooltip />
        <Bar dataKey="value" fill={color} radius={[4, 4, 0, 0]} barSize={8} />
      </BarChart>
    </ResponsiveContainer>
  );
}
