/**
 * رسوم قانونية خطية بلون الشعار: واجهة محكمة بأعمدة، وميزان أمامها يتأرجح ثم يستقر عند الظهور،
 * ومعيّنات من زوايا الشعار. زخرفية فقط (aria-hidden)، وتتوقف حركتها عند تقليل الحركة.
 */
export function CourtScene({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 400 300" className={className} aria-hidden fill="none" stroke="#303741" strokeLinecap="round" strokeLinejoin="round">
      {/* معيّنات خلفية */}
      <path className="scene-ring" d="M200 6 334 140 200 274 66 140Z" strokeOpacity=".08" strokeWidth="1.2" />
      <path className="scene-ring r2" d="M200 40 300 140 200 240 100 140Z" strokeOpacity=".1" strokeWidth="1.2" />
      {/* واجهة المحكمة: جملون، أعمدة، درج */}
      <g className="art-rise" strokeOpacity=".38" strokeWidth="1.6">
        <path d="M110 112 200 62 290 112Z" />
        <path d="M104 118h192M104 124h192" />
        <path d="M126 130v96M150 130v96M250 130v96M274 130v96" />
        <path d="M120 232h160M110 242h180M100 252h200" />
        <path d="M200 80l7 7-7 7-7-7Z" fill="#303741" fillOpacity=".25" />
      </g>
      {/* الميزان أمام الواجهة */}
      <g strokeWidth="2" strokeOpacity=".75">
        <path className="scene-draw" style={{ ['--len' as string]: 180 }} d="M200 120v104M178 226h44" />
        <g className="art-beam">
          <path d="M150 128h100" />
          <path d="M200 116l6 6-6 6-6-6Z" fill="#303741" />
        </g>
        <g className="art-pan-a"><path d="M156 128 144 158M156 128l12 30M138 158a18 7 0 0 0 36 0Z" fill="#e1e5e6" /></g>
        <g className="art-pan-b"><path d="M244 128 232 158M244 128l12 30M226 158a18 7 0 0 0 36 0Z" fill="#e1e5e6" /></g>
      </g>
    </svg>
  );
}

/** فاصل زخرفي: خطان ومعيّن في الوسط */
export function DiamondRule({ className }: { className?: string }) {
  return (
    <div className={className} aria-hidden>
      <div className="flex items-center gap-3 text-control">
        <span className="h-px flex-1 bg-current" /><span className="diamond text-ink/40" /><span className="h-px flex-1 bg-current" />
      </div>
    </div>
  );
}
