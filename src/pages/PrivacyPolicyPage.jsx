import React from 'react'

export default function PrivacyPolicyPage() {
  return (
    <div className="min-h-screen pt-28 pb-12 lg:pt-36 lg:pb-20">
      <div className="page-container max-w-4xl mx-auto">
        <h1 className="font-display text-4xl lg:text-5xl text-spa-dark font-bold mb-8">
          Política de Gestão de Saúde e Privacidade
        </h1>

        <div className="bg-white rounded-xl3 p-8 lg:p-12 shadow-card space-y-8 font-body text-spa-muted text-base leading-relaxed">
          
          <section>
            <h2 className="text-2xl font-display text-spa-dark font-semibold mb-4">1. Introdução</h2>
            <p>
              Bem-vindo(a) ao Day Spa Hochheim. A sua privacidade e a gestão adequada dos seus dados de saúde 
              são de extrema importância para nós. Esta Política de Gestão de Saúde e Privacidade descreve como 
              coletamos, usamos, armazenamos e protegemos as suas informações pessoais e de saúde, garantindo a 
              conformidade com a Lei Geral de Proteção de Dados (LGPD).
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-display text-spa-dark font-semibold mb-4">2. Coleta de Informações</h2>
            <p className="mb-2">Coletamos informações necessárias para a prestação de nossos serviços e terapias, incluindo:</p>
            <ul className="list-disc pl-5 space-y-2">
              <li><strong>Dados Cadastrais:</strong> Nome completo, CPF, data de nascimento, e-mail, telefone.</li>
              <li><strong>Dados de Saúde:</strong> Informações sobre alergias, histórico médico básico relevante para as terapias, condições físicas atuais e preferências de tratamento.</li>
              <li><strong>Dados de Pagamento:</strong> Informações transacionais (processadas de forma segura por nossos parceiros de pagamento).</li>
            </ul>
          </section>

          <section>
            <h2 className="text-2xl font-display text-spa-dark font-semibold mb-4">3. Uso das Informações</h2>
            <p className="mb-2">As informações coletadas são utilizadas exclusivamente para:</p>
            <ul className="list-disc pl-5 space-y-2">
              <li>Agendamento e confirmação de serviços e terapias.</li>
              <li>Personalização do atendimento, garantindo que as terapias sejam seguras e adequadas ao seu estado de saúde atual.</li>
              <li>Comunicações importantes, como lembretes de agendamento via WhatsApp ou e-mail.</li>
              <li>Emissão de notas fiscais e controle financeiro.</li>
            </ul>
          </section>

          <section>
            <h2 className="text-2xl font-display text-spa-dark font-semibold mb-4">4. Compartilhamento de Dados</h2>
            <p>
              Seus dados de saúde e informações pessoais <strong>não serão vendidos, alugados ou compartilhados</strong> com terceiros para fins de marketing. O compartilhamento ocorre apenas quando necessário com prestadores de serviços essenciais (como plataformas de pagamento e sistemas de emissão de notas fiscais) ou por exigência legal.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-display text-spa-dark font-semibold mb-4">5. Segurança e Armazenamento</h2>
            <p>
              Adotamos medidas técnicas e administrativas rigorosas para proteger seus dados contra acessos não autorizados, perdas ou alterações. Seus dados de saúde são armazenados em sistemas seguros, com acesso restrito apenas aos profissionais da Day Spa Hochheim diretamente envolvidos no seu atendimento.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-display text-spa-dark font-semibold mb-4">6. Seus Direitos</h2>
            <p className="mb-2">De acordo com a LGPD, você tem o direito de:</p>
            <ul className="list-disc pl-5 space-y-2">
              <li>Acessar as informações que temos sobre você.</li>
              <li>Solicitar a correção de dados incompletos, inexatos ou desatualizados.</li>
              <li>Solicitar a exclusão ou anonimização de dados desnecessários ou excessivos, ressalvadas as obrigações de guarda legal.</li>
              <li>Revogar o consentimento a qualquer momento.</li>
            </ul>
          </section>

          <section>
            <h2 className="text-2xl font-display text-spa-dark font-semibold mb-4">7. Contato</h2>
            <p>
              Se tiver dúvidas sobre esta política ou desejar exercer seus direitos em relação aos seus dados, entre em contato conosco:
            </p>
            <p className="mt-2 font-medium">
              E-mail: contato@spahochheim.com.br <br />
              WhatsApp: (47) 99115-1707
            </p>
          </section>
          
        </div>
      </div>
    </div>
  )
}
