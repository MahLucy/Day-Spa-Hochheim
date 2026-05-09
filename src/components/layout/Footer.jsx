import { Link } from 'react-router-dom'
import { MapPin, Clock, Instagram, Linkedin, MessageSquare } from 'lucide-react'

export default function Footer() {
  return (
    <footer className="bg-spa-dark text-white py-10 md:py-12">
      <div className="max-w-[92%] md:max-w-[80%] lg:max-w-[75%] mx-auto">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-10 text-center md:text-left items-center">
          {/* Coluna 1 - Logo + frase */}
          <div className="flex flex-col items-center md:items-start gap-3">
            <img
              src="/logo.png"
              alt="Logo Hochheim"
              className="w-[7rem] md:w-[150px] h-auto"
              draggable={false}
            />
            <p className="max-w-[20rem] text-[0.8rem] md:text-xl leading-[1.5] opacity-90 font-extralight">
              Cuidado técnico, estrutura completa e foco absoluto na sua saúde.
            </p>
          </div>

          {/* Coluna 2 - Menu rápido */}
          <div className="flex flex-col items-center">
            <h3 className="font-display font-black uppercase mb-2 border-b-2 border-white inline-block text-[0.8rem] md:text-xl">
              Menu Rápido
            </h3>
            <div className="flex flex-col items-center gap-2 mt-3">
              <Link
                to="/"
                className="text-[0.8rem] md:text-xl font-extralight hover:underline"
              >
                Sobre
              </Link>
              <Link
                to="/servicos"
                className="text-[0.8rem] md:text-xl font-extralight hover:underline"
              >
                Serviços
              </Link>
              <a
                href="https://hochheimkids.com.br"
                target="_blank"
                rel="noreferrer"
                className="text-[0.8rem] md:text-xl font-extralight hover:underline"
              >
                Kids Hochheim
              </a>
              <Link
                to="/politica-de-privacidade"
                className="text-[0.8rem] md:text-xl font-extralight hover:underline break-words"
              >
                Política de Privacidade
              </Link>
            </div>
          </div>

          {/* Coluna 3 - Contato + redes */}
          <div className="flex flex-col items-center md:items-center gap-2">
            <p className="text-[0.8rem] md:text-xl font-extralight">Blumenau - SC</p>
            <p className="text-[0.8rem] md:text-xl font-extralight break-words">
              contato@spahochheim.com.br
            </p>
            <p className="text-[0.8rem] md:text-xl font-extralight">(47) 3222-0010</p>

            <div className="flex items-center justify-center gap-3 pt-2">
              <a href="https://www.linkedin.com/company/hochheim-hidro-e-terapias/?originalSubdomain=br" target="_blank" rel="noreferrer" className="hover:opacity-80">
                <img
              src="icons/linkedin.png"
              alt="Linkedin"
              className="w-12 h-12 md:w-16 md:h-16"
               draggable={false}
            />
              </a>
              <a href="https://wa.me/5547999833291?text=Ol%C3%A1%2C%20gostaria%20de%20saber%20mais%20sobre%20a%20cl%C3%ADnica!" target="_blank" rel="noreferrer" className="hover:opacity-80">
                <img
              src="icons/whatsapp.png"
              alt="WhatsApp"
              className="w-12 h-12 md:w-16 md:h-16"
               draggable={false}
            />
              </a>
              <a href="https://www.instagram.com/clinicahochheim" target="_blank" rel="noreferrer" className="hover:opacity-80">
                <img
              src="icons/instagram.png"
              alt="Instagram"
              className="w-12 h-12 md:w-16 md:h-16"
               draggable={false}
            />
              </a>
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 mt-10 pt-6 text-[0.7rem] md:text-xl opacity-80">
          <p className="text-center md:text-left">Copyright © todos os direitos reservados</p>
          <p className="text-center md:text-right">
            Desenvolvido por <b>Agência Publiquei</b>
          </p>
        </div>
      </div>
    </footer>
  )
}
