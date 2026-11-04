export type Option<V = string | number> = { value: V; label: string; [k: string]: unknown };

export type SharedProps = {
  office: { name: string; shortName: string; tagline: string; license: string };
  env: string | null;
  flash: { success?: string | null; error?: string | null; status?: string | null; inviteUrl?: string | null };
  auth: { user: { id: number; name: string; email: string; role: 'lawyer' | 'client'; twoFactor: boolean } } | null;
  nav?: { claimsInbox: number };
  portal?: { clients: { id: number; name: string; canView: boolean; canSubmitClaims: boolean }[] };
  errors: Record<string, string>;
};

export type Doc = {
  id: string; name: string; label?: string | null; category?: string; categoryLabel?: string; party?: string; partyLabel?: string;
  size: number; extension?: string; scan: string; shared?: boolean; uploadedAt?: string; url: string | null; owner?: string | null;
};

export type Paginated<T> = {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
  total: number; from: number | null; to: number | null; current_page: number; last_page: number;
};

export type Totals = Record<string, Record<string, string>>;
