import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ReactNode } from 'react';
import OfficeLayout from '@/Layouts/OfficeLayout';
import PortalLayout from '@/Layouts/PortalLayout';

type PageModule = { default: { (props: any): ReactNode; layout?: (page: ReactNode) => ReactNode } };

createInertiaApp({
  title: (title) => (title ? `${title} — فيصل حميضان الحميضان للمحاماة` : 'فيصل حميضان الحميضان للمحاماة'),
  resolve: (name) => {
    const pages = import.meta.glob<PageModule>('./Pages/**/*.tsx', { eager: true });
    const page = pages[`./Pages/${name}.tsx`];
    if (!page) throw new Error('Page not found: ' + name);
    if (page.default.layout === undefined) {
      if (name.startsWith('Office/') || name.startsWith('Admin/')) page.default.layout = (p: ReactNode) => <OfficeLayout>{p}</OfficeLayout>;
      else if (name.startsWith('Portal/')) page.default.layout = (p: ReactNode) => <PortalLayout>{p}</PortalLayout>;
    }
    return page;
  },
  setup({ el, App, props }) {
    createRoot(el!).render(<App {...props} />);
  },
  progress: { color: '#1d6b62', delay: 200 },
});
