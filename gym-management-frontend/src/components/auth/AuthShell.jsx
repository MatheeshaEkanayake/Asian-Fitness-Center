import ImageCarousel from '../shared/ImageCarousel'
import ThemeToggle from '../shared/ThemeToggle'
import { useBranding } from '../../context/BrandingContext'

const CAROUSEL_IMAGES = [
  { src: '/login_carousal/undraw_personal-trainer_bqkg.svg', alt: 'Personal trainer guiding a member' },
  { src: '/login_carousal/undraw_morning-workout_73u9.svg', alt: 'Member completing a morning workout' },
  { src: '/login_carousal/undraw_fitness-stats_bd09.svg', alt: 'Fitness progress statistics' },
  { src: '/login_carousal/undraw_yoga_i399.svg', alt: 'Member practicing yoga' },
]

/**
 * Shared frame for the signed-out pages (LoginPage, SignupPage): branding
 * panel with the image carousel on the left, form panel on the right.
 *
 * On desktop the page is pinned to the viewport and only the form panel
 * scrolls, so a long form (signup) keeps the carousel in view. `wide` gives
 * the form panel more room for the two-column member form.
 */
export default function AuthShell({ heading, blurb, subtitle, wide = false, children }) {
  const { branding } = useBranding()

  return (
    <div className="brand-maroon relative min-h-screen md:h-dvh flex flex-col md:flex-row bg-[color:var(--color-paper)]">
      <ThemeToggle className="absolute right-4 top-4 z-10" />

      {/* Branding panel */}
      <div
        className={`hidden md:flex ${
          wide ? 'md:flex-[2]' : 'md:flex-[3]'
        } flex-col items-center justify-center bg-[color:var(--color-brand-deep)] px-10 py-12 text-center relative`}
      >
        <div
          className="absolute inset-0 opacity-10"
          style={{
            backgroundImage:
              'radial-gradient(circle at 20% 20%, white 0, transparent 45%), radial-gradient(circle at 80% 75%, white 0, transparent 40%)',
          }}
        />
        <div className="relative w-full max-w-md h-[33rem] ">
          <ImageCarousel images={CAROUSEL_IMAGES} />
        </div>
        <h1 className="font-display text-3xl md:text-4xl font-bold text-white relative">{heading}</h1>
        <p className="mt-4 max-w-md text-sm text-white/80 relative">{blurb}</p>
        <p className="mt-10 text-xs text-white/60 relative">
          © {new Date().getFullYear()} {branding.name}. All rights reserved.
        </p>
      </div>

      {/* Form panel — m-auto (not items-center) so a form taller than the
          panel scrolls from its top instead of being clipped. */}
      <div className={`flex ${wide ? 'flex-[3]' : 'flex-[2]'} md:overflow-y-auto px-6 py-12`}>
        <div className={`m-auto w-full ${wide ? 'max-w-2xl' : 'max-w-sm'}`}>
          <div className="mb-8 text-center md:text-left">
            <img
              src={branding.logoUrl}
              alt={`${branding.name} logo`}
              className="h-14 w-auto mx-auto md:mx-0 mb-4"
            />
            <h2 className="font-display text-xl font-semibold text-[color:var(--color-ink)]">
              {branding.name}
            </h2>
            {branding.tagline && (
              <p className="mt-0.5 text-xs font-medium uppercase tracking-wider text-[color:var(--color-ink-faint)]">
                {branding.tagline}
              </p>
            )}
            <p className="mt-1 text-sm text-[color:var(--color-ink-soft)]">{subtitle}</p>
          </div>

          {children}
        </div>
      </div>
    </div>
  )
}
