import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import {
  ChevronRight,
  ChevronLeft,
  Star,
  Send,
  MapPin,
  Phone,
} from "lucide-react";
import ServiceCard from "../components/ui/ServiceCard";
import { services, testimonials } from "../data/services";

function HeroSection() {
  return (
    <section className="relative overflow-hidden bg-[url('/header.webp')] bg-no-repeat bg-center bg-cover h-[100vh] flex items-center">
      <div className="w-full px-4 sm:px-8 lg:px-20 xl:px-80 relative py-10 md:py-12 lg:py-14">
        <div className="max-w-xl text-spa-dark text-left">
          <p className="text-xs font-body font-medium tracking-widest uppercase mb-4">
            CLÍNICA HOCHHEIM • SPA
          </p>

          <h1 className="font-display text-3xl sm:text-4xl lg:text-6xl font-bold leading-tight mb-6">
            Experiências de spa para relaxar corpo e mente
          </h1>

          <p className="text-sm md:text-xl font-body leading-relaxed mb-8 max-w-lg">
            Massagens, terapias e cuidados personalizados para o seu bem-estar e
            equilíbrio.
          </p>

          <div className="flex flex-wrap gap-3">
            <Link to="/servicos" className="btn-primary">
              Ver Serviços
            </Link>
            <a href="#contact" className="btn-outline">
              Fale Conosco
            </a>
          </div>
        </div>
      </div>
    </section>
  );
}

function StatsSection() {
  const stats = [
    {
      value: "9+",
      label: (
        <>
          Tipos de
          <br />
          vivência
        </>
      ),
    },
    {
      value: "10+",
      label: (
        <>
          Anos de
          <br />
          história
        </>
      ),
    },
    {
      value: "100%",
      label: (
        <>
          Bem-estar
          <br />
          garantido
        </>
      ),
    },
  ];

  return (
    <section className="py-12 lg:py-16 bg-white">
      <div className="w-full px-4 sm:px-8 lg:px-20 xl:px-80 relative">
        <div className="grid grid-cols-3 gap-2 sm:gap-6 divide-x divide-gray-100">
          {stats.map((s, idx) => (
            <div key={idx} className="text-center px-1 sm:px-4">
              <p className="font-display text-xl sm:text-3xl md:text-4xl lg:text-6xl text-spa-dark font-semibold">
                {s.value}
              </p>
              <p className="text-xs sm:text-sm md:text-base lg:text-lg font-subtitle text-spa-dark mt-1 leading-tight">
                {s.label}
              </p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function AboutSection() {
  return (
    <section className="py-12 lg:py-16 bg-white">
      <div className="w-full px-4 sm:px-8 lg:px-20 xl:px-80 relative">
        <div className="bg-spa-dark rounded-[32px] px-6 py-8 md:px-12 md:py-12 max-w-6xl mx-auto">
          <p className="text-white font-subtitle text-base md:text-2xl leading-relaxed">
            O SPA da Clínica Hochheim foi pensado para proporcionar momentos de {" "}
            <strong className="font-title">
              relaxamento profundo, alívio de tensões e autocuidado.
            </strong>{" "}
            Com técnicas especializadas e ambiente acolhedor, oferecemos
            experiências completas para o seu bem-estar.
          </p>
        </div>
      </div>
    </section>
  );
}

function ServicesSection() {
  const items = [
    {
      title: "Cuidado",
      img: "https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=800&q=80",
    },
    {
      title: "Saúde",
      img: "https://images.unsplash.com/photo-1552693673-1bf958298935?w=800&q=80",
    },
    {
      title: "Massagem",
      img: "https://images.unsplash.com/photo-1519823551278-64ac92734fb1?w=800&q=80",
    },
    {
      title: "Terapia",
      img: "https://images.unsplash.com/photo-1506126613408-eca07ce68773?w=800&q=80",
    },
  ];

  return (
    <section className="py-16 lg:py-24 bg-white">
      <div className="page-container">
        {/* Título alinhado à esquerda com linha decorativa centralizada verticalmente */}
        <div className="flex items-center gap-4 mb-12">
          <div className="w-16 sm:w-24 h-[5px] bg-spa-accent rounded-full shrink-0" />
          <h2 className="font-display text-2xl sm:text-3xl md:text-4xl font-bold text-spa-dark">
            O que temos a oferecer:
          </h2>
        </div>

        {/* Cards grid - retangulares (mais altos) */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-5">
          {items.map((item, index) => (
            <Link
              to="/servicos"
              key={index}
              className="relative h-[220px] sm:h-[280px] md:h-[500px] rounded-3xl overflow-hidden group cursor-pointer block"
            >
              {/* Imagem */}
              <img
                src={item.img}
                alt={item.title}
                className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
              />

              {/* Overlay com gradiente suave */}
              <div className="absolute inset-0 bg-gradient-to-t from-spa-dark/80 via-spa-dark/40 to-spa-dark/20 group-hover:from-spa-dark/80 group-hover:via-spa-dark/40 transition-all duration-300" />

              {/* Texto centralizado */}
              <div className="absolute inset-0 flex items-center justify-center">
                <p className="text-white font-display text-lg sm:text-xl md:text-3xl font-bold tracking-wide drop-shadow-lg">
                  {item.title}
                </p>
              </div>
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
}
function HighlightsSection() {
  const [idx, setIdx] = useState(0);
  const highlighted = services.slice(0, 6);

  // Visible cards per breakpoint: 1 mobile, 2 tablet, 3 desktop
  const getVisibleCount = () => {
    if (typeof window === "undefined") return 3;
    if (window.innerWidth >= 1024) return 3;
    if (window.innerWidth >= 640) return 2;
    return 1;
  };

  const [visibleCount, setVisibleCount] = useState(getVisibleCount);

  // Update on resize
  useEffect(() => {
    const handleResize = () => setVisibleCount(getVisibleCount());
    window.addEventListener("resize", handleResize);
    return () => window.removeEventListener("resize", handleResize);
  }, []);

  const maxIdx = Math.max(0, highlighted.length - visibleCount);

  const slide = (dir) => {
    setIdx((prev) => Math.max(0, Math.min(maxIdx, prev + dir)));
  };

  // Card width as percentage (accounting for gaps)
  // gap = 20px (gap-5), card occupies 1/visibleCount of container minus gaps
  const gap = 20;

  return (
    <section className="py-16 lg:py-24 bg-[#f5f5f5]">
      <div className="page-container">
        {/* Header row */}
        <div className="flex items-end justify-between mb-10">
          {/* Title with decorative line */}
          <div className="flex items-center gap-4">
            <div className="w-16 sm:w-24 h-[5px] bg-spa-accent rounded-full shrink-0" />
            <h2 className="font-display text-2xl sm:text-3xl md:text-4xl font-bold text-spa-dark">
              Conheça os destaques
            </h2>
          </div>

          {/* Navigation arrows */}
          <div className="flex gap-2">
            <button
              onClick={() => slide(-1)}
              disabled={idx === 0}
              className="w-10 h-10 rounded-full border-2 border-spa-dark flex items-center justify-center hover:bg-spa-dark hover:text-white text-spa-dark disabled:opacity-30 transition-all duration-200"
            >
              <ChevronLeft size={18} />
            </button>
            <button
              onClick={() => slide(1)}
              disabled={idx >= maxIdx}
              className="w-10 h-10 rounded-full bg-spa-mid border-2 border-spa-mid text-white flex items-center justify-center hover:bg-spa-dark hover:border-spa-dark disabled:opacity-30 transition-all duration-200"
            >
              <ChevronRight size={18} />
            </button>
          </div>
        </div>

        {/* Cards carousel */}
        <div className="overflow-hidden">
          <div
            className="flex transition-transform duration-500 ease-in-out"
            style={{
              gap: `${gap}px`,
              transform: `translateX(calc(-${idx} * (100% / ${visibleCount} + ${gap - gap / visibleCount}px)))`,
            }}
          >
            {highlighted.map((s) => (
              <div
                key={s.id}
                className="shrink-0"
                style={{
                  width: `calc((100% - ${gap * (visibleCount - 1)}px) / ${visibleCount})`,
                }}
              >
                <ServiceCard service={s} />
              </div>
            ))}
          </div>
        </div>

        {/* Dot indicators */}
        <div className="flex justify-center gap-2 mt-6">
          {Array.from({ length: maxIdx + 1 }).map((_, i) => (
            <button
              key={i}
              onClick={() => setIdx(i)}
              className={`h-2 rounded-full transition-all duration-300 ${
                i === idx ? "w-8 bg-spa-dark" : "w-2 bg-gray-300 hover:bg-gray-400"
              }`}
            />
          ))}
        </div>
      </div>
    </section>
  );
}

function GiftCardSection() {
  return (
    <section className="py-18 lg:py-18 bg-spa-dark">
      <div className="page-container">
        <div className="px-6 md:px-16 py-14 md:py-20">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-12 lg:gap-20 items-center">
            {/* Left: Text content */}
            <div>
              <h2 className="font-display text-3xl sm:text-4xl lg:text-[48px] text-white font-bold mb-8">
                Ofereça uma experiência inesquecível
              </h2>
              <p className="text-sm md:text-2xl text-white/70 font-body font-extralight leading-relaxed mb-10 max-w-md">
                Nossos Cartões Presente permitem presentear alguém especial com uma vivência de bem-estar completa. Personalize o valor e o momento.
              </p>
              <Link
                to="/carrinho"
                className="inline-flex items-center gap-2 bg-spa-mid text-white font-body font-medium px-8 py-4 rounded-full hover:bg-[#1a6d8c] transition-all duration-200 active:scale-95 text-sm md:text-base tracking-wide"
              >
                Comprar Cartão Presente
              </Link>
            </div>

          {/* Right: Gift card image */}
            <div className="flex justify-center md:justify-end">
                <img
                  src="/cartao-presente.webp"
                  alt="Cartão Presente - Clínica Hochheim SPA"
                  className="w-full h-full object-cover scale-[1.08]"
                />
              </div>
            </div>
          </div>
          </div>
    </section>
  );
}

function SpaceSection() {
  const spaces = [
    {
      label: "Sala do\ncuidado",
      img: "https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=800&q=80",
    },
    {
      label: "Ambiente\nrelaxante",
      img: "https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=800&q=80",
    },
    {
      label: "Espaço\nzen",
      img: "https://images.unsplash.com/photo-1561485132-59468537c8d8?w=800&q=80",
    },
    {
      label: "Sala de\nmassagem",
      img: "https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=800&q=80",
    },
    {
      label: "Recepção\nacolhedora",
      img: "https://images.unsplash.com/photo-1591343395082-e120087004b4?w=800&q=80",
    },
    {
      label: "Área de\ndescanso",
      img: "https://images.unsplash.com/photo-1515377905703-c4788e51af15?w=800&q=80",
    },
  ];

  const [visible, setVisible] = useState(false);
  const sectionRef = useState(null);

  useEffect(() => {
    const node = sectionRef[0];
    if (!node) return;
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setVisible(true);
          observer.disconnect();
        }
      },
      { threshold: 0.15 }
    );
    observer.observe(node);
    return () => observer.disconnect();
  }, [sectionRef[0]]);

  return (
    <section
      ref={(el) => (sectionRef[0] = el)}
      className="bg-spa-cream pt-16 lg:pt-24"
    >
      <div className="page-container pb-10">
        {/* Title with decorative line */}
        <div
          className={`flex items-center gap-4 transition-all duration-700 ease-out ${
            visible
              ? "opacity-100 translate-y-0"
              : "opacity-0 translate-y-6"
          }`}
        >
          <div className="w-16 sm:w-24 h-[5px] bg-spa-accent rounded-full shrink-0" />
          <h2 className="font-display text-2xl sm:text-3xl md:text-4xl font-bold text-spa-dark">
            Nosso espaço
          </h2>
        </div>
      </div>

      {/* 3x2 Grid - Full width, zero gap */}
      <div className="w-full grid grid-cols-2 lg:grid-cols-3 gap-0">
        {spaces.map((s, i) => (
          <div
            key={i}
            className={`relative overflow-hidden group cursor-pointer transition-all duration-1000 ease-out ${
              visible
                ? "opacity-100 translate-y-0"
                : "opacity-0 translate-y-10"
            }`}
             style={{
               transitionDelay: visible ? `${100 + (i % 3) * 150}ms` : "0ms",
               height: "clamp(260px, 30vw, 420px)",
             }}
          >
            {/* Image with hover zoom */}
            <img
              src={s.img}
              alt={s.label.replace('\n', ' ')}
              className="w-full h-full object-cover transition-transform duration-[1.5s] ease-out group-hover:scale-110"
            />

            {/* Overlay */}
            <div 
              className={`absolute inset-0 transition-colors duration-700 ease-out ${
                i === 0 
                  ? "bg-spa-dark/75" 
                  : "bg-black/20 group-hover:bg-spa-dark/75"
              }`} 
            />

            {/* Label (Centered) */}
            <div className="absolute inset-0 flex items-center justify-center p-6">
              <p 
                className={`text-white font-display text-center leading-[1.2] whitespace-pre-line drop-shadow-md transition-all duration-700 ease-out ${
                  i === 0 
                    ? "text-lg sm:text-xl md:text-2xl font-extralight opacity-100 translate-y-0" 
                    : "text-lg sm:text-lg md:text-2xl font-extralight opacity-0 translate-y-4 group-hover:opacity-100 group-hover:translate-y-0"
                }`}
              >
                {s.label}
              </p>
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}

const defaultTestimonials = [
  {
    nome: "Nome",
    texto:
      "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.",
  },
  {
    nome: "Nome",
    texto:
      "Lorem dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.",
    marginTop: "3rem",
  },
  {
    nome: "Nome",
    texto:
      "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.",
  },
];

function TestimonialsSection({ itens = defaultTestimonials }) {
  return (
    <section className="bg-spa-pale py-12 md:py-16">
      <div className="w-[92%] md:w-[80%] lg:w-[70%] mx-auto">
        {/* Título + divisor */}
        <div className="flex flex-col items-center gap-2 mb-[3rem] md:mb-0">
          <h2 className="text-lg md:text-2xl text-spa-dark tracking-wide font-bold uppercase leading-none">
            DEPOIMENTOS
          </h2>
          <div className="flex items-center gap-2">
            <div className="w-16 h-1 bg-spa-mid rounded-full" />
            <div className="w-2.5 h-2.5 bg-spa-mid rotate-45 rounded-[2px]" />
            <div className="w-16 h-1 bg-spa-mid rounded-full" />
          </div>
        </div>

        {/* Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-[4rem] items-start md:mt-12">
          {itens.map((d, i) => (
            <div
              key={i}
              className="bg-white border border-spa-dark/20 rounded-2xl p-5 md:p-[3.5rem] shadow-sm md:[margin-top:var(--md-mt)]"
              style={{ "--md-mt": d.marginTop || "0px" }}
            >
              <div className="flex flex-col items-start gap-4">
                <div className="flex items-center gap-4">
                  {/* Avatar */}
                  <div className="w-12 h-12 md:w-16 md:h-16 rounded-full border-[3px] border-spa-mid overflow-hidden flex items-center justify-center bg-spa-pale shrink-0">
                    {d.foto ? (
                      <img
                        src={d.foto}
                        alt={d.nome}
                        className="w-full h-full object-cover"
                      />
                    ) : (
                      <span className="font-bold text-spa-dark text-lg md:text-xl">
                        {d.nome.charAt(0)}
                      </span>
                    )}
                  </div>

                  <p className="font-bold text-spa-dark text-lg md:text-xl">
                    {d.nome}
                  </p>
                </div>

                <p className="text-spa-text font-extralight leading-[1.7] text-justify">
                  {d.texto}
                </p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function ContactSection() {
  const [form, setForm] = useState({
    name: "",
    phone: "",
    email: "",
    message: "",
  });
  const [sent, setSent] = useState(false);

  const handleSubmit = (e) => {
    e.preventDefault();
    setSent(true);
    setTimeout(() => setSent(false), 3000);
  };

  return (
    <section id="contact" className="py-12 lg:py-24 bg-white">
      <div className="page-container">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-16 lg:gap-24">
          {/* Info */}
          <div>
            <h2 className="font-display text-2xl sm:text-3xl md:text-4xl text-spa-dark font-bold mb-8">
              Contato
            </h2>
            <div className="space-y-6">
              {/* Local */}
              <div className="flex items-start gap-4">
                <div className="w-10 h-10 bg-spa-pale rounded-full flex items-center justify-center shrink-0 mt-1">
                  <MapPin size={18} className="text-spa-mid" />
                </div>
                <div>
                  <p className="font-display font-semibold text-spa-dark text-lg mb-1">
                    Local
                  </p>
                  <p className="text-sm font-body text-spa-text/70 leading-relaxed max-w-[200px]">
                    Rua Itaiópolis, 102 - Blumenau/SC, CEP: 89012-084
                  </p>
                </div>
              </div>

              {/* Contato (Telefones) */}
              <div className="flex items-start gap-4">
                <div className="w-10 h-10 bg-spa-pale rounded-full flex items-center justify-center shrink-0 mt-1">
                  <Phone size={18} className="text-spa-mid" />
                </div>
                <div>
                  <p className="font-display font-semibold text-spa-dark text-lg mb-1">
                    Contato
                  </p>
                  <p className="text-sm font-body text-spa-text/70">
                    (47) 3037-1707
                    <br />
                    (47) 99115-1707
                  </p>
                </div>
              </div>
            </div>

            {/* Horário de funcionamento Card */}
            <div className="bg-spa-pale mt-10 border border-spa-dark/20 shadow-sm rounded-2xl p-6 md:p-8 max-w-sm">
              <h3 className="font-display font-semibold text-spa-dark text-lg mb-4">
                Horário de funcionamento
              </h3>
              <div className="space-y-3 text-sm font-body text-spa-text/80">
                <div className="flex justify-between items-center border-b border-spa-dark/20 pb-3">
                  <span className="font-semibold text-spa-dark">Segunda - Sexta</span>
                  <span>13h às 21h</span>
                </div>
                <div className="flex justify-between items-center border-b border-spa-dark/20 pb-3">
                  <span className="font-semibold text-spa-dark">Sábado</span>
                  <span className="font-extralight">Fechado</span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="font-semibold text-spa-dark">Domingo</span>
                  <span className="font-extralight">Fechado</span>
                </div>
              </div>
            </div>
          </div>

          {/* Form */}
          <div>
            <div className="flex items-end gap-3 mb-8">
              <h2 className="font-display text-2xl sm:text-3xl md:text-4xl text-spa-dark font-bold leading-none">
                Fale Conosco
              </h2>
              <div className="w-12 h-1 bg-spa-accent mb-2" />
            </div>
            
            {sent ? (
              <div className="bg-teal-50 border border-teal-200 rounded-xl p-8 text-center">
                <p className="text-spa-dark font-body font-medium text-lg">
                  Mensagem enviada com sucesso!
                </p>
                <p className="text-sm text-spa-text/70 font-body mt-2">
                  Em breve nossa equipe entrará em contato.
                </p>
              </div>
            ) : (
              <form onSubmit={handleSubmit} className="space-y-4">
                <input
                  className="input-field shadow-sm"
                  placeholder="Nome"
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  required
                />
                <input
                  className="input-field shadow-sm"
                  placeholder="Telefone"
                  value={form.phone}
                  onChange={(e) => setForm({ ...form, phone: e.target.value })}
                />
                <input
                  type="email"
                  className="input-field shadow-sm"
                  placeholder="E-mail"
                  value={form.email}
                  onChange={(e) => setForm({ ...form, email: e.target.value })}
                  required
                />
                <textarea
                  className="input-field shadow-sm resize-none"
                  rows={5}
                  placeholder="Assunto/Mensagem"
                  value={form.message}
                  onChange={(e) =>
                    setForm({ ...form, message: e.target.value })
                  }
                  required
                />
                <button
                  type="submit"
                  className="btn-primary w-full flex items-center justify-center gap-2 py-4 shadow-sm"
                >
                  <Send size={18} />
                  Enviar mensagem
                </button>
              </form>
            )}
          </div>
        </div>
      </div>
    </section>
  );
}

export default function HomePage() {
  return (
    <>
      <HeroSection />
      <StatsSection />
      <AboutSection />
      <ServicesSection />
      <HighlightsSection />
      <GiftCardSection />
      <SpaceSection />
      <TestimonialsSection />
      <ContactSection />
    </>
  );
}
